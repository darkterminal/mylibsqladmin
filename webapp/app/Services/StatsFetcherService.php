<?php

namespace App\Services;

use App\Models\QueryMetric;
use App\Models\SlowestQuery;
use App\Models\TopQuery;
use App\Models\UserDatabase;
use Illuminate\Support\Facades\Cache;

class StatsFetcherService
{
    /**
     * The Studio calls this once per executed statement. A remote HTTP fetch
     * plus metric inserts must not run unthrottled in the request cycle —
     * gate it to once per TTL per database (or globally for broadcasts).
     */
    public static function run(?string $databaseName = null, ?string $source = null, ?int $userId = null): string|array
    {
        $throttleKey = $databaseName
            ? 'stats-throttle:db:' . md5($databaseName)
            : 'stats-throttle:broadcast-all';
        $throttleTtl = $databaseName ? 30 : 60;

        if (! Cache::add($throttleKey, true, $throttleTtl)) {
            return $databaseName ? ['throttled' => true] : 'Stats throttled';
        }

        if ($databaseName) {

            if (UserDatabase::where('database_name', $databaseName)->doesntExist()) {
                logger("Database not found: {$databaseName}");
                return "Database not found";
            }

            return self::broadcastToDatabase($databaseName, $source, $userId);
        } else {
            return self::broadcastStatsChanged($source, $userId);
        }
    }

    protected static function broadcastStatsChanged($source, $userId): string
    {
        $databases = SqldService::getDatabases(config('mylibsqladmin.local_instance'), $userId);
        foreach ($databases as $database) {

            $host = SqldService::useEndpoint('db', $source);
            $request = SqldService::createBaseRequest();
            $response = $request->get("{$host}/v1/namespaces/{$database['database_name']}/stats");
            $stats = $response->json();

            $queryMetric = QueryMetric::insertGetId([
                'database_id' => $database['id'],
                'rows_read_count' => $stats['rows_read_count'],
                'rows_written_count' => $stats['rows_written_count'],
                'storage_bytes_used' => $stats['storage_bytes_used'],
                'write_requests_delegated' => $stats['write_requests_delegated'],
                'replication_index' => $stats['replication_index'],
                'embedded_replica_frames_replicated' => $stats['embedded_replica_frames_replicated'],
                'query_count' => $stats['query_count'],
                'elapsed_ms' => is_double($stats['elapsed_ms']) ? $stats['elapsed_ms'] : json_decode($stats['elapsed_ms'], true)['sum'],
                'queries' => $stats['queries'] === null ? null : json_encode($stats['queries']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            logger('Fetched stats for ' . $database['database_name']);

            foreach ($stats['top_queries'] as $query) {
                TopQuery::insert([
                    'main_id' => $queryMetric,
                    'rows_written' => $query['rows_written'],
                    'rows_read' => $query['rows_read'],
                    'query' => $query['query'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            logger('Fetched top query for ' . $database['database_name']);

            foreach ($stats['slowest_queries'] as $query) {
                SlowestQuery::insert([
                    'main_id' => $queryMetric,
                    'rows_written' => $query['rows_written'],
                    'rows_read' => $query['rows_read'],
                    'query' => $query['query'],
                    'elapsed_ms' => $query['elapsed_ms'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            logger('Fetched slowest query for ' . $database['database_name']);
        }

        logger('Stats fetched');
        return 'Stats fetched';
    }

    protected static function broadcastToDatabase(string $databaseName, string $source, int $userId): array
    {
        $databases = SqldService::getDatabases(config('mylibsqladmin.local_instance'), $userId);
        $database = collect($databases)->where('database_name', $databaseName)->first();

        $host = SqldService::useEndpoint('db', $source);
        $request = SqldService::createBaseRequest();

        $response = $request->get("{$host}/v1/namespaces/{$databaseName}/stats");
        $stats = $response->json();

        $queryMetric = QueryMetric::insertGetId([
            'database_id' => $database['id'],
            'rows_read_count' => $stats['rows_read_count'],
            'rows_written_count' => $stats['rows_written_count'],
            'storage_bytes_used' => $stats['storage_bytes_used'],
            'write_requests_delegated' => $stats['write_requests_delegated'],
            'replication_index' => $stats['replication_index'],
            'embedded_replica_frames_replicated' => $stats['embedded_replica_frames_replicated'],
            'query_count' => $stats['query_count'],
            'elapsed_ms' => is_double($stats['elapsed_ms']) ? $stats['elapsed_ms'] : json_decode($stats['elapsed_ms'], true)['sum'],
            'queries' => $stats['queries'] === null ? null : json_encode($stats['queries']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        logger("Fetched stats for {$databaseName}");

        foreach ($stats['top_queries'] as $query) {
            TopQuery::insert([
                'main_id' => $queryMetric,
                'rows_written' => $query['rows_written'],
                'rows_read' => $query['rows_read'],
                'query' => $query['query'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        logger("Fetched top query for {$databaseName}");

        foreach ($stats['slowest_queries'] as $query) {
            SlowestQuery::insert([
                'main_id' => $queryMetric,
                'rows_written' => $query['rows_written'],
                'rows_read' => $query['rows_read'],
                'query' => $query['query'],
                'elapsed_ms' => $query['elapsed_ms'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        logger("Fetched slowest query for $databaseName");

        return $stats;
    }
}
