<?php

namespace App\Services;

use App\Models\QueryMetric;
use App\Models\SlowestQuery;
use App\Models\TopQuery;
use Illuminate\Support\Facades\Http;

class StatsFetcherService
{
    public static function run(?string $databaseName = null): string|array
    {
        if ($databaseName) {
            return self::broadcastToDatabase($databaseName);
        } else {
            return self::broadcastStatsChanged();
        }
    }

    protected static function broadcastStatsChanged(): string
    {
        $databases = SqldService::getDatabases();
        foreach ($databases as $database) {
            $response = Http::get("http://db:8081/v1/namespaces/{$database['database_name']}/stats");
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

    protected static function broadcastToDatabase(string $databaseName): array
    {
        $databases = SqldService::getDatabases();
        $database = collect($databases)->where('database_name', $databaseName)->first();

        $response = Http::get("http://db:8081/v1/namespaces/{$databaseName}/stats");
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
