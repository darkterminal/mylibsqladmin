<?php

namespace App\Services;

use App\Models\GroupDatabase;
use App\Models\UserDatabase;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SqldService
{
    public static function useEndpoint(string $service, ?string $source = null): string|false
    {
        switch ($service) {
            case 'db':
                $host = $source === 'web' && app()->isProduction() ? env('LIBSQL_HOST', 'db') : config('mylibsqladmin.libsql.api.host');
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
        logger()->debug("Entering getDatabases with local: $local");
        $sapi = php_sapi_name();

        $allDatabases = $local
            ? self::getLocalDatabases()
            : self::getRemoteDatabases();

        if (!str_starts_with($sapi, 'cli') || $sapi === 'frankenphp') {
            logger()->debug("User is not authenticated and not running in CLI, source $sapi");
            return $allDatabases;
        }

        logger()->debug("is local: $local, Fetched databases: " . json_encode($allDatabases));

        $userId = auth()->check() ? auth()->user()->id : null;
        logger()->debug("User ID: " . ($userId ?? 'null'));

        if ($userId && (!str_starts_with($sapi, 'cli') || $sapi === 'frankenphp')) {
            logger()->debug("Syncing databases with user");
            self::syncDatabasesWithUser($userId, $allDatabases);
        }

        $result = $userId
            ? UserDatabase::where('user_id', $userId)->get()->toArray()
            : [];

        logger()->debug("Returning databases: " . json_encode($result));

        return $result;
    }

    public static function getLocalDatabases(): array
    {
        logger()->debug("Fetching databases from local instance");

        $allDatabases = UserDatabase::whereNotIn('database_name', ['default'])
            ->get()
            ->collect()
            ->toArray();

        return $allDatabases;
    }

    public static function getRemoteDatabases(): array
    {
        logger()->debug("Fetching databases from remote endpoint");

        $allDatabases = [];
        $host = self::useEndpoint('db');
        $request = self::createBaseRequest();

        logger()->debug("Attempting to fetch metrics from $host");
        $allMetrics = $request->retry(3, 100)
            ->timeout(10)
            ->get("$host/metrics");

        if ($allMetrics->successful()) {
            logger()->debug("Metrics fetch successful, parsing response");
            $allDatabases = self::parseMetricsResponse($allMetrics->body());

            if (empty($allDatabases)) {
                logger()->debug("No databases found, performing health checks");
                $userDatabases = UserDatabase::where('user_id', auth()->user()->id)
                    ->whereNotIn('database_name', ['default'])
                    ->get();

                $retryMetrics = self::performHealthChecks($host, $request, $userDatabases);
                if ($retryMetrics->successful()) {
                    logger()->debug("Health checks successful, parsing retry metrics");
                    $allDatabases = self::parseMetricsResponse($retryMetrics->body());
                } else {
                    logger()->debug("Health checks failed on retry");
                }
            }
        } else {
            logger()->debug("Metrics fetch failed");
        }

        return $allDatabases;
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
    public static function archiveDatabase(string $database, ?string $source = null): bool
    {
        $deletedCount = UserDatabase::where('database_name', $database)
            ->where('user_id', auth()->user()->id)
            ->delete();

        if (!$deletedCount) {
            logger()->error("No user database found: $database");
            return false;
        }

        $host = self::useEndpoint('db', $source);

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

    public static function restoreDatabase(string $database, ?string $source = null): bool
    {
        $restored = UserDatabase::where('database_name', $database)
            ->where('user_id', auth()->user()->id)
            ->restore();

        if (!$restored) {
            logger()->error("No user database found: $database");
            return false;
        }

        $host = self::useEndpoint('db', $source);

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

    public static function createDatabase(string $database, mixed $isSchema, int $groupId, int $teamId, ?string $source = null): bool
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

        $host = self::useEndpoint('db', $source);
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

    public static function deleteDatabase(string $database, ?string $source = null): bool
    {
        $archivedDatabase = null;
        $userDatabase = UserDatabase::where('database_name', $database)
            ->where('user_id', auth()->user()->id)
            ->onlyTrashed()
            ->first();

        if ($userDatabase) {
            $archivedDatabase = "$database-archived";
        }

        $deletedCount = UserDatabase::where('database_name', $database)
            ->where('user_id', auth()->user()->id)
            ->forceDelete();

        if (!$deletedCount) {
            logger()->error("No user database found: $database");
            return false;
        }

        $databaseShouldBeDeleted = $archivedDatabase ?? $database;
        $host = self::useEndpoint('db', $source);
        $request = self::createBaseRequest();
        $response = $request->delete("$host/v1/namespaces/$databaseShouldBeDeleted");

        if ($response->status() !== 200) {
            logger()->error("SQLD deletion failed for: $databaseShouldBeDeleted");
            return false;
        }

        logger()->info("Deleted database: $databaseShouldBeDeleted");
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

                if (in_array(php_sapi_name(), ['cli', 'frankenphp'])) {
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
