<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hash every existing bearer token in place (SHA-256 hex). Tokens whose value
 * already looks like a hash (exactly 64 hex chars) are left untouched. After
 * this migration, bearer strings are usable only by whoever held them, and
 * the database discloses nothing usable to an attacker reading a backup.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['user_database_tokens', 'group_database_tokens'] as $table) {
            DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    $patch = [];
                    foreach (['full_access_token', 'read_only_token'] as $column) {
                        $value = (string) ($row->{$column} ?? '');
                        if ($value === '' || preg_match('/^[0-9a-f]{64}$/', $value)) {
                            continue; // already a hash
                        }
                        $patch[$column] = hash('sha256', $value);
                    }
                    if ($patch !== []) {
                        DB::table($table)->where('id', $row->id)->update($patch);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Irreversible by design: un-hashing would require the original
        // plaintext values, which this migration deliberately discards.
    }
};
