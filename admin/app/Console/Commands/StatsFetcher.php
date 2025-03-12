<?php

namespace App\Console\Commands;

use App\Models\QueryMetric;
use App\Models\SlowestQuery;
use App\Models\TopQuery;
use App\Services\SqldService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class StatsFetcher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:stats-fetcher';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch each database statistics';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $databases = SqldService::getDatabases();
        foreach ($databases as $database) {
            $response = Http::get('http://localhost:8081/v1/namespaces/' . $database['database_name'] . '/stats');
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
    }
}
