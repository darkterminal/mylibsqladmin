<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Re-base every naive datetime column written by the application (in the
 * previous APP_TIMEZONE) to UTC.
 *
 * Storage moves to UTC unconditionally; APP_TIMEZONE is demoted to a display
 * concern. Rows written before this migration ran were stamped in the
 * installation's APP_TIMEZONE (Asia/Jakarta by default), so they are shifted
 * into UTC here.
 *
 * Scope notes:
 * - jobs.failed_at is written by SQLite's CURRENT_TIMESTAMP (already UTC).
 * - jobs.created_at / job_batches.created_at are INTEGER epoch seconds.
 * - sessions/cache/cache_locks hold INTEGER epoch only.
 * Both families are intentionally excluded.
 *
 * This migration is IRREVERSIBLE. Re-running it is impossible by construction
 * (Laravel tracks it in the migrations table). It is fail-closed: any
 * unparseable value aborts the whole transaction.
 */
return new class extends Migration
{
    /** @var array<string, string[]> */
    private array $columns = [
        'activity_logs' => ['created_at', 'updated_at', 'deleted_at'],
        'permissions' => ['created_at', 'updated_at'],
        'personal_access_tokens' => ['expires_at', 'created_at', 'updated_at', 'last_used_at'],
        'query_metrics' => ['created_at', 'updated_at'],
        'database_user_permissions' => ['created_at', 'updated_at'],
        'role_user' => ['created_at', 'updated_at'],
        'roles' => ['created_at', 'updated_at'],
        'granted_user_databases' => ['created_at', 'updated_at', 'deleted_at'],
        'slowest_queries' => ['created_at', 'updated_at'],
        'group_database_tokens' => ['created_at', 'updated_at', 'deleted_at'],
        'team_user' => ['created_at', 'updated_at'],
        'group_databases' => ['created_at', 'updated_at', 'deleted_at'],
        'teams' => ['created_at', 'updated_at', 'deleted_at'],
        'invitations' => ['expires_at', 'created_at', 'updated_at', 'deleted_at'],
        'top_queries' => ['created_at', 'updated_at'],
        // jobs.failed_at deliberately omitted (SQLite CURRENT_TIMESTAMP is UTC)
        'user_activity_logs' => ['timestamp', 'created_at', 'updated_at'],
        'user_database_tokens' => ['created_at', 'updated_at', 'deleted_at'],
        'user_databases' => ['created_at', 'updated_at', 'deleted_at'],
        'password_reset_tokens' => ['created_at'],
        'users' => ['email_verified_at', 'created_at', 'updated_at', 'deleted_at'],
        'permission_role' => ['created_at', 'updated_at'],
    ];

    public function up(): void
    {
        // Source timezone of the values about to be re-based: the APP_TIMEZONE
        // the installation used when writing them.
        $fromTz = (string) env('APP_TIMEZONE', 'Asia/Jakarta');
        $source = new DateTimeZone($fromTz);
        $utc = new DateTimeZone('UTC');

        $total = 0;

        DB::transaction(function () use ($source, $utc, &$total) {
            foreach ($this->columns as $table => $cols) {
                if (count($cols) === 0) {
                    continue;
                }

                DB::table($table)
                    ->selectRaw('rowid as r, *')
                    ->orderBy('rowid')
                    ->lazy(500)
                    ->each(function ($row) use ($table, $cols, $source, $utc, &$total) {
                        foreach ($cols as $col) {
                            $value = $row->{$col} ?? null;
                            if (!is_string($value) || trim($value) === '') {
                                continue;
                            }

                            try {
                                $converted = (new DateTimeImmutable($value, $source))
                                    ->setTimezone($utc)
                                    ->format('Y-m-d H:i:s');
                            } catch (\Throwable $e) {
                                throw new RuntimeException(
                                    "Refusing to re-base {$table}.{$col}: unparseable value '{$value}' ({$e->getMessage()})"
                                );
                            }

                            DB::table($table)
                                ->where('rowid', $row->r)
                                ->update([$col => $converted]);
                            $total++;
                        }
                    });
            }
        });

        // Migration completion is recorded by Laravel in the migrations table.
        // The total number of converted values is intentionally not printed.
    }

    public function down(): void
    {
        throw new RuntimeException(
            'This migration is irreversible: timestamps were re-based to UTC in place. Restore from backup to undo.'
        );
    }
};
