<?php

/**
 * StatsFetcherService against a faked sqld — asserts the metric insert
 * shape the dashboard consumes.
 */

use App\Models\User;
use App\Models\QueryMetric;
use App\Models\SlowestQuery;
use App\Models\TopQuery;
use App\Services\StatsFetcherService;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('stats broadcast for a named database inserts metric rows with the sqld payload shape', function () {
    $user = User::factory()->create();
    $dbName = 'stats-db-' . uniqid();

    \App\Models\UserDatabase::create([
        'user_id' => $user->id,
        'database_name' => $dbName,
        'is_schema' => '0',
        'created_by' => $user->id,
    ]);

    $statsPayload = [
        'rows_read_count' => 1200,
        'rows_written_count' => 300,
        'storage_bytes_used' => 8192,
        'write_requests_delegated' => 0,
        'replication_index' => 42,
        'embedded_replica_frames_replicated' => 0,
        'query_count' => 77,
        'elapsed_ms' => 123.4,
        'queries' => ['something'],
        'top_queries' => [
            ['rows_written' => 10, 'rows_read' => 500, 'query' => 'select * from t'],
            ['rows_written' => 4, 'rows_read' => 200, 'query' => 'select 1'],
        ],
        'slowest_queries' => [
            ['rows_written' => 10, 'rows_read' => 500, 'query' => 'select * from t', 'elapsed_ms' => 150.5],
        ],
    ];

    Http::preventStrayRequests();
    Http::fake(['*v1/namespaces/*/stats' => Http::response($statsPayload, 200)]);

    StatsFetcherService::run($dbName, 'web', $user->id);

    $metric = QueryMetric::whereHas('database', fn ($q) => $q->where('database_name', $dbName))->firstOrFail();

    expect((int) $metric->rows_read_count)->toBe(1200)
        ->and((int) $metric->rows_written_count)->toBe(300)
        ->and((int) $metric->storage_bytes_used)->toBe(8192)
        ->and((int) $metric->query_count)->toBe(77);

    expect(TopQuery::where('main_id', $metric->id)->count())->toBe(2);
    expect(SlowestQuery::where('main_id', $metric->id)->count())->toBe(1);
    expect(SlowestQuery::where('main_id', $metric->id)->first()->elapsed_ms)->toEqual(150.5);
});

test('stats broadcast is throttled per database', function () {
    $user = User::factory()->create();
    $dbName = 'stats-throttle-' . uniqid();

    \App\Models\UserDatabase::create([
        'user_id' => $user->id,
        'database_name' => $dbName,
        'is_schema' => '0',
        'created_by' => $user->id,
    ]);

    $statsPayload = [
        'rows_read_count' => 1,
        'rows_written_count' => 1,
        'storage_bytes_used' => 1,
        'write_requests_delegated' => 0,
        'replication_index' => 1,
        'embedded_replica_frames_replicated' => 0,
        'query_count' => 1,
        'elapsed_ms' => 1.0,
        'queries' => null,
        'top_queries' => [],
        'slowest_queries' => [],
    ];

    Http::preventStrayRequests();
    Http::fake(['*v1/namespaces/*/stats' => Http::response($statsPayload, 200)]);

    StatsFetcherService::run($dbName, 'web', $user->id);
    StatsFetcherService::run($dbName, 'web', $user->id);

    $metric = QueryMetric::whereHas('database', fn ($q) => $q->where('database_name', $dbName));

    // Only one fetch actually hit the (faked) network within the TTL window.
    expect($metric->count())->toBe(1);
});
