<?php

namespace App\Services;

use App\Models\GroupDatabase;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SqldService
{
    private static function isRunningInDocker(): bool
    {
        return file_exists('/.dockerenv') ||
            (is_readable('/proc/self/cgroup') &&
                strpos(file_get_contents('/proc/self/cgroup'), 'docker') !== false);
    }

    public static function useEndpoint(string $service): string|false
    {
        $isDocker = self::isRunningInDocker();

        switch ($service) {
            case 'db':
                return $isDocker ? 'http://db:8081' : 'http://localhost:8081';
            case 'bridge':
                return $isDocker ? 'http://bridge:4500' : 'http://localhost:4500';
            default:
                throw new \BadMethodCallException("Unknown endpoint", 1);
        }
    }

    public static function getDatabases(): array
    {
        $host = self::useEndpoint('bridge');
        $databases = Http::retry(5, 100)->withHeaders([
            'Authorization' => 'realm=' . env('BRIDGE_HTTP_PASSWORD', 'libsql'),
            'Content-Type' => 'application/json',
        ])
            ->get("$host/api/databases")
            ->collect()
            ->toArray();

        if (php_sapi_name() === 'cli') {
            $allDatabases = [];
            foreach ($databases as $database) {
                if ($database['name'] !== 'default') {
                    $userDatabase = UserDatabase::where('database_name', $database['name'])->first();
                    if ($userDatabase) {
                        $allDatabases[] = $userDatabase->toArray();
                    }
                }
            }
            return $allDatabases;
        }

        if (!auth()->check()) {
            return [];
        }

        $userId = auth()->user()->id;

        foreach ($databases as $database) {
            if ($database['name'] !== 'default') {
                UserDatabase::updateOrInsert(
                    ['user_id' => $userId, 'database_name' => $database['name']],
                    ['user_id' => $userId, 'database_name' => $database['name']]
                );
            }
        }

        return UserDatabase::where('user_id', $userId)->get()->toArray();

    }

    /**
     * Archive a database
     *
     * @param string $database The name of the database to archive
     * @return bool True if archiving was successful, false otherwise
     */
    public static function archiveDatabase(string $database): bool
    {
        $deletedCount = UserDatabase::where('database_name', $database)
            ->where('user_id', auth()->user()->id)
            ->delete();

        if (!$deletedCount) {
            logger()->error("No user database found: $database");
            return false;
        }

        $host = self::useEndpoint('bridge');

        try {
            $result = Http::retry(5, 100)->withHeaders([
                'Authorization' => 'realm=' . env('BRIDGE_HTTP_PASSWORD', 'libsql'),
                'Content-Type' => 'application/json',
            ])
                ->post("$host/api/database/archive", [
                    'name' => $database
                ])
                ->throw()
                ->json();

            // Check if result contains success flag
            return $result['success'] ?? false;
        } catch (RequestException $e) {
            // Log the error for debugging
            Log::error('Failed to archive database', [
                'database' => $database,
                'status' => $e->response->status(),
                'error' => $e->response->json()['error'] ?? $e->getMessage()
            ]);

            return false;
        }
    }

    public static function restoreDatabase(string $database): bool
    {
        $restored = UserDatabase::where('database_name', $database)
            ->where('user_id', auth()->user()->id)
            ->restore();

        if (!$restored) {
            logger()->error("No user database found: $database");
            return false;
        }

        $host = self::useEndpoint('bridge');

        try {
            $result = Http::retry(5, 100)->withHeaders([
                'Authorization' => 'realm=' . env('BRIDGE_HTTP_PASSWORD', 'libsql'),
                'Content-Type' => 'application/json',
            ])
                ->post("$host/api/database/restore", [
                    'name' => $database
                ])
                ->throw()
                ->json();

            // Check if result contains success flag
            return $result['success'] ?? false;
        } catch (RequestException $e) {
            // Log the error for debugging
            Log::error('Failed to restore database', [
                'database' => $database,
                'status' => $e->response->status(),
                'error' => $e->response->json()['error'] ?? $e->getMessage()
            ]);

            return false;
        }
    }

    public static function createDatabase(string $database, mixed $isSchema, int $groupId, int $teamId): bool
    {
        $host = self::useEndpoint('db');
        if (is_bool($isSchema)) {
            $data['shared_schema'] = $isSchema;
        } else {
            $data['shared_schema_name'] = $isSchema;
        }

        // check if database name is contains word archived in the end of the name database_archived/database-archived/databasearchived
        if (preg_match('/[._-]?archived$/i', $database)) {
            return false;
        }

        $request = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])
            ->post("$host/v1/namespaces/$database/create", $data);

        if ($request->failed()) {
            return false;
        }

        $userDatabase = UserDatabase::create([
            'user_id' => auth()->user()->id,
            'team_id' => $teamId,
            'database_name' => $database,
            'is_schema' => $isSchema
        ]);

        if (!$userDatabase) {
            return false;
        }

        $group = GroupDatabase::findOrFail($groupId);
        $groupDatabase = $group->members()->create([
            'group_id' => $groupId,
            'team_id' => $teamId
        ]);

        if (!$groupDatabase) {
            return false;
        }

        return true;
    }

    public static function deleteDatabase(string $database): bool
    {
        $deletedCount = UserDatabase::where('database_name', $database)
            ->where('user_id', auth()->user()->id)
            ->delete();

        if (!$deletedCount) {
            logger()->error("No user database found: $database");
            return false;
        }

        $host = self::useEndpoint('db');
        $request = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->delete("$host/v1/namespaces/$database");

        if ($request->getStatusCode() !== 200) {
            logger()->error("SQLD deletion failed for: $database");
            return false;
        }

        logger()->info("Deleted database: $database");
        return true;
    }

    public static function deleteDatabaseExcept(string $database): void
    {
        $host = self::useEndpoint('db');
        $databases = self::getDatabases();

        foreach ($databases as $db) {
            if ($db['database_name'] === $database) {
                Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])
                    ->delete("$host/v1/namespaces/" . $db['database_name']);

                UserDatabase::where('database_name', $database)
                    ->where('user_id', auth()->user()->id)
                    ->delete();
            }
        }
    }
}
