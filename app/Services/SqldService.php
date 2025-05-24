<?php

namespace App\Services;

use App\Models\GroupDatabase;
use App\Models\Team;
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
                $host = config('mylibsqladmin.libsql.api.host');
                $port = config('mylibsqladmin.libsql.api.port');

                return "http://{$host}" . ($port === null ? '' : ":{$port}");
            case 'bridge':
                return 'http://' . config('mylibsqladmin.bridge.host') . ':' . config('mylibsqladmin.bridge.port');
            default:
                return false;
        }
    }

    public static function getDatabases(bool $local = true): array
    {
        if (!auth()->check() && !str_starts_with(php_sapi_name(), 'cli')) {
            return [];
        }

        switch ($local) {
            case false:
                $host = self::useEndpoint('db');
                $request = self::createBaseRequest();

                // First metrics fetch attempt
                $allMetrics = $request->retry(3, 100)
                    ->timeout(10)
                    ->get("$host/metrics");

                $allDatabases = [];
                if ($allMetrics->successful()) {
                    $allDatabases = self::parseMetricsResponse($allMetrics->body());

                    // Trigger health checks if no databases found
                    if (empty($allDatabases)) {
                        $userDatabases = UserDatabase::where('user_id', auth()->user()->id)
                            ->whereNotIn('database_name', ['default'])
                            ->get();

                        $retryMetrics = self::performHealthChecks($host, $request, $userDatabases);
                        $allDatabases = self::parseMetricsResponse($retryMetrics->successful() ? $retryMetrics->body() : '');
                    }
                }
                break;
            case true:
                $allDatabases = UserDatabase::whereNotIn('database_name', ['default'])
                    ->get()
                    ->collect()
                    ->toArray();
                break;
        }

        logger()->debug("is local: $local, Fetched databases: " . json_encode($allDatabases));

        $userId = auth()->check() ? auth()->user()->id : null;

        if ($userId && !str_starts_with(php_sapi_name(), 'cli')) {
            self::syncDatabasesWithUser($userId, $allDatabases);
        }

        return $userId
            ? UserDatabase::where('user_id', $userId)->get()->toArray()
            : [];
    }

    private static function performHealthChecks(string $host, $request, $userDatabases)
    {
        foreach ($userDatabases as $db) {
            try {
                $pipelineRequest = [
                    'requests' => [
                        ['type' => 'execute', 'stmt' => ['sql' => 'SELECT 1']],
                        ['type' => 'close']
                    ]
                ];

                self::createBaseRequest()
                    ->timeout(3)
                    ->post("{$db->database_name}.{$host}/v2/pipeline", $pipelineRequest);
            } catch (\Exception $e) {
                logger()->error($e->getMessage());
            }
        }

        return $request->retry(3, 100)
            ->timeout(10)
            ->get("$host/metrics");
    }

    private static function parseMetricsResponse(string $metricsBody): array
    {
        preg_match_all('/namespace="([^"]+)"/', $metricsBody, $matches);

        $databases = [];
        foreach ($matches[1] ?? [] as $match) {
            $active = preg_match('/-archived$/', $match) ? 'inactive' : 'active';
            $databases[] = [
                'name' => $match,
                'status' => $active,
                'path' => $match
            ];
        }

        return $databases;
    }

    private static function syncDatabasesWithUser(int $userId, array $databases): void
    {
        foreach ($databases as $database) {
            $updateData = [
                'user_id' => $userId,
                'database_name' => $database['name'] ?? $database['database_name']
            ];

            if ($database['deleted_at'] !== null) {
                $updateData['deleted_at'] = now()->format('Y-m-d H:i:s');
            }

            UserDatabase::updateOrInsert(
                ['user_id' => $userId, 'database_name' => $database['name'] ?? $database['database_name']],
                $updateData
            );
        }
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

        $host = self::useEndpoint('db');

        try {
            $request = self::createBaseRequest();
            $response = $request->retry(5, 100)
                ->post("$host/v1/namespaces/$database/fork/$database-archived");

            if ($response->status() === 200) {
                $deleteResponse = $request->delete("$host/v1/namespaces/$database");
                return $deleteResponse->status() === 200;
            }

            return false;
        } catch (RequestException $e) {
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

        $host = self::useEndpoint('db');

        try {
            $request = self::createBaseRequest();

            $result = $request->retry(5, 100)
                ->post("$host/v1/namespaces/$database-archived/fork/$database");

            if ($result->status() == 200) {
                $result = $request->delete("$host/v1/namespaces/$database-archived");
                return $result->status() == 200 ? true : false;
            }

            return false;
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

        // check if database name is contains word archived in the end of the name database_archived/database-archived/databasearchived
        if (preg_match('/[._-]?archived$/i', $database)) {
            return false;
        }

        $host = self::useEndpoint('db');
        $request = self::createBaseRequest();
        $response = $request->post("$host/v1/namespaces/$database/create", $data);

        if ($response->failed()) {
            return false;
        }

        $userDatabase = UserDatabase::create([
            'user_id' => auth()->user()->id,
            'team_id' => $teamId,
            'database_name' => $database,
            'is_schema' => $isSchema,
            'created_by' => auth()->user()->id
        ]);

        if (!$userDatabase) {
            return false;
        }

        $group = GroupDatabase::findOrFail($groupId);
        $group->members()->attach($userDatabase->id, [
            'group_id' => $groupId,
            'database_id' => $userDatabase->id
        ]);

        return true;
    }

    public static function deleteDatabase(string $database): bool
    {
        $deletedCount = UserDatabase::where('database_name', $database)
            ->where('user_id', auth()->user()->id)
            ->forceDelete();

        if (!$deletedCount) {
            logger()->error("No user database found: $database");
            return false;
        }

        $host = self::useEndpoint('db');
        $request = self::createBaseRequest();
        $response = $request->delete("$host/v1/namespaces/$database");

        if ($response->status() !== 200) {
            logger()->error("SQLD deletion failed for: $database");
            return false;
        }

        logger()->info("Deleted database: $database");
        return true;
    }

    public static function deleteDatabaseExcept(string $database): void
    {
        $host = self::useEndpoint('db');
        $databases = self::getDatabases(config('mylibsqladmin.local_instance'));

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
        if (!empty(config('mylibsqladmin.libsql.api.username')) && !empty(config('mylibsqladmin.libsql.api.password'))) {
            $request = Http::withBasicAuth(config('mylibsqladmin.libsql.api.username'), config('mylibsqladmin.libsql.api.password'))
                ->accept('application/json');
        } else {
            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
            ]);
        }

        return $request;
    }

}
