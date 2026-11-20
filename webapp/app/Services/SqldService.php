<?php

namespace App\Services;

use App\Models\GroupDatabase;
use App\Models\UserDatabase;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SqldService
{
    public static function useEndpoint(string $service, ?string $source = null): string|false
    {
        switch ($service) {
            case 'db':
                // Admin calls (create/delete/stats) always target the sqld
                // admin API. That is what LIBSQL_API_HOST/LIBSQL_API_PORT
                // are for. The client-facing LIBSQL_HOST (hrana) is a
                // different endpoint — dev installs happen to work with
                // LIBSQL_HOST=db because the admin API lives on the same
                // host, but fresh installs that follow the LLI docs
                // (LIBSQL_HOST=proxy) hit a closed port and 500.
                $host = config('mylibsqladmin.libsql.api.host');
                $port = config('mylibsqladmin.libsql.api.port');

                $scheme = filter_var(env('LIBSQL_API_USE_HTTPS', false), FILTER_VALIDATE_BOOLEAN) ? 'https' : 'http';

                return "{$scheme}://{$host}" . ($port === null ? '' : ":{$port}");
            case 'bridge':
                $scheme = filter_var(env('LIBSQL_API_USE_HTTPS', false), FILTER_VALIDATE_BOOLEAN) ? 'https' : 'http';

                return $scheme . '://' . config('mylibsqladmin.bridge.host') . ':' . config('mylibsqladmin.bridge.port');
            default:
                return false;
        }
    }

    public static function getDatabases(bool $local = true, ?int $userId = null): array
    {
        logger()->debug("Entering getDatabases with local: $local");

        $allDatabases = $local ? self::getLocalDatabases() : self::getRemoteDatabases();

        logger()->debug("Fetched databases for user $userId");

        logger()->debug("is local: $local, Fetched databases: " . json_encode($allDatabases));

        logger()->debug("User ID: " . ($userId ?? 'null'));

        if ($userId && UserDatabase::whereIn('database_name', Arr::pluck($allDatabases, 'name'))->where('user_id', $userId)->doesntExist()) {
            logger()->debug("Syncing databases with user");
            self::syncDatabasesWithUser($userId, $allDatabases);
        }

        $result = $userId
            ? UserDatabase::where('user_id', $userId)->get()->toArray()
            : [];

        logger()->debug("Returning databases: {$userId} (count=" . count($result) . ")");

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
            logger()->debug("Fetched databases: " . count($allDatabases) . " entries");

            if (empty($allDatabases)) {
                logger()->debug("No databases found, performing health checks");
                $currentUserId = auth()->user()?->id;
                $userDatabases = $currentUserId === null
                    ? collect()
                    : UserDatabase::where('user_id', $currentUserId)
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
        try {
            $pipelineRequest = [
                'requests' => [
                    ['type' => 'execute', 'stmt' => ['sql' => 'SELECT 1']],
                    ['type' => 'close']
                ]
            ];

            self::createBaseRequest()
                ->timeout(3)
                ->post("{$host}/v2/pipeline", $pipelineRequest);
        } catch (\Exception $e) {
            logger()->error('internal error', ['file' => basename(__FILE__)]);
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

            if (isset($database['deleted_at'])) {
                $updateData['deleted_at'] = now()->format('Y-m-d H:i:s');
            }

            UserDatabase::updateOrInsert(
                ['user_id' => $userId, 'database_name' => $database['name'] ?? $database['database_name'], 'created_by' => $userId],
                $updateData
            );
        }
    }

    /**
     * Archive a database
     *
     * @param string $database The name of the database to archive
     * @param int $userId Owner of the authorized record (not necessarily the caller)
     * @return bool True if archiving was successful, false otherwise
     */
    public static function archiveDatabase(string $database, int $userId, ?string $source = null): bool
    {
        $userDatabase = UserDatabase::where('database_name', $database)
            ->where('user_id', $userId)
            ->first();

        if (!$userDatabase) {
            logger()->error("No user database found: $database");
            return false;
        }

        $host = self::useEndpoint('db', $source);

        // Fork the sqld namespace BEFORE touching the local record: if the
        // fork fails, the database stays fully visible and nothing is lost.
        try {
            $request = self::createBaseRequest();
            $response = $request->retry(5, 100)
                ->post("$host/v1/namespaces/$database/fork/$database-archived");

            if ($response->status() !== 200) {
                Log::error('Failed to fork database during archive', [
                    'database' => $database,
                    'status' => $response->status(),
                ]);
                return false;
            }

            $deleteResponse = $request->delete("$host/v1/namespaces/$database");

            if ($deleteResponse->status() !== 200) {
                // The archived copy exists in sqld; keep it archived and log.
                Log::warning('Fork succeeded but namespace delete did not return 200', [
                    'database' => $database,
                    'status' => $deleteResponse->status(),
                ]);
            }
        } catch (RequestException $e) {
            Log::error('Failed to archive database', [
                'database' => $database,
                'status' => $e->response->status(),
                'error' => $e->response->json()['error'] ?? $e->getMessage()
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('Failed to archive database', [
                'database' => $database,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $userDatabase->delete();

        return true;
    }

    public static function restoreDatabase(string $database, int $userId, ?string $source = null): bool
    {
        $userDatabase = UserDatabase::where('database_name', $database)
            ->where('user_id', $userId)
            ->onlyTrashed()
            ->first();

        if (!$userDatabase) {
            logger()->error("No user database found: $database");
            return false;
        }

        $host = self::useEndpoint('db', $source);

        // Fork the archived namespace back BEFORE touching the local record:
        // if the fork fails, the database stays archived and nothing is lost.
        try {
            $request = self::createBaseRequest();

            $result = $request->retry(5, 100)
                ->post("$host/v1/namespaces/$database-archived/fork/$database");

            if ($result->status() !== 200) {
                Log::error('Failed to fork the archived namespace back', [
                    'database' => $database,
                    'status' => $result->status(),
                ]);

                return false;
            }

            $deleteResponse = $request->delete("$host/v1/namespaces/$database-archived");

            if ($deleteResponse->status() !== 200) {
                Log::warning('Archived namespace fork succeeded but delete did not return 200', [
                    'database' => $database,
                    'status' => $deleteResponse->status(),
                ]);
            }
        } catch (RequestException $e) {
            // Log the error for debugging
            Log::error('Failed to restore database', [
                'database' => $database,
                'status' => $e->response->status(),
                'error' => $e->response->json()['error'] ?? $e->getMessage()
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('Failed to restore database', [
                'database' => $database,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $userDatabase->restore();

        return true;
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

    public static function deleteDatabase(string $database, int $userId, ?string $source = null): bool
    {
        $archivedDatabase = null;
        $userDatabase = UserDatabase::where('database_name', $database)
            ->where('user_id', $userId)
            ->onlyTrashed()
            ->first();

        if ($userDatabase) {
            $archivedDatabase = "$database-archived";
        }

        $databaseShouldBeDeleted = $archivedDatabase ?? $database;
        $host = self::useEndpoint('db', $source);
        $request = self::createBaseRequest();

        // Delete the sqld namespace BEFORE the local record: if it fails,
        // the database stays safely archived (or listed) — never lost.
        try {
            $response = $request->delete("$host/v1/namespaces/$databaseShouldBeDeleted");
        } catch (\Throwable $e) {
            Log::error('SQLD deletion threw', [
                'database' => $databaseShouldBeDeleted,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($response->status() !== 200) {
            logger()->error("SQLD deletion failed for: $databaseShouldBeDeleted");
            return false;
        }

        $deletedCount = UserDatabase::where('database_name', $database)
            ->where('user_id', $userId)
            ->forceDelete();

        if (!$deletedCount) {
            logger()->error("No user database found: $database");
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
