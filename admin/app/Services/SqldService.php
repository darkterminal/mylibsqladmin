<?php

namespace App\Services;

use App\Models\GroupDatabase;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDatabase;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SqldService
{

    public static function useEndpoint(string $service): string|false
    {
        switch ($service) {
            case 'db':
                return 'http://' . config('mylibsqladmin.libsql.api.host') . ':' . config('mylibsqladmin.libsql.api.port');
            case 'bridge':
                return 'http://' . config('mylibsqladmin.bridge.host') . ':' . config('mylibsqladmin.bridge.port');
            default:
                throw new \BadMethodCallException("Unknown endpoint", 1);
        }
    }

    public static function getDatabases(): array
    {
        $host = self::useEndpoint('bridge');
        $databases = Http::retry(5, 100)->withHeaders([
            'Authorization' => 'realm=' . config('mylibsqladmin.bridge.password'),
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
                'Authorization' => 'realm=' . config('mylibsqladmin.bridge.password'),
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
                'Authorization' => 'realm=' . config('mylibsqladmin.bridge.password'),
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
        if (is_bool($isSchema)) {
            $data['shared_schema'] = $isSchema;
        } else {
            $data['shared_schema_name'] = $isSchema;
        }

        $host = self::useEndpoint('db');
        $request = self::createBaseRequest();
        $request->post("$host/v1/namespaces/$database/create", $data);

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
        $group->members()->attach(auth()->user()->id, [
            'group_id' => $groupId,
            'database_id' => $userDatabase->id,
        ]);

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
        $request = self::createBaseRequest();
        $request->delete("$host/v1/namespaces/$database");

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
            if ($db['database_name'] !== $database) {

                $request = self::createBaseRequest();
                $request->delete("$host/v1/namespaces/" . $db['database_name']);

                $userDatabase = UserDatabase::where('database_name', $database);

                if (php_sapi_name() === 'cli') {
                    $userDatabase->delete();
                }

                $userDatabase->orWhere('user_id', auth()->user()->id)
                    ->delete();
            }
        }
    }

    public static function createBaseRequest(): PendingRequest
    {
        if (!empty(config('mylibsqladmin.libsql.username')) && !empty(config('mylibsqladmin.libsql.password'))) {
            $request = Http::withBasicAuth(config('mylibsqladmin.libsql.username'), config('mylibsqladmin.libsql.password'))
                ->accept('application/json');
        } else {
            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
            ]);
        }
        return $request;
    }
}
