<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/dashboard')->assertOk();
});

test('expensive dashboard props are deferred — not computed on first load', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('databases')
            ->missing('databaseMetrics')
            ->missing('mostUsedDatabases')
        );
});

test('deferred dashboard props resolve on a partial reload', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reloaded) => $reloaded
                ->has('databaseMetrics')
                ->has('mostUsedDatabases'))
        );
});

test('recent activity is deferred and visible to its owner', function () {
    $user = User::factory()->create();

    \App\Models\UserActivityLog::create([
        'user_id' => $user->id,
        'type' => 'login',
        'description' => 'User logged in from 127.0.0.1',
        'timestamp' => now(),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->missing('recentActivity')
            ->loadDeferredProps(fn (Assert $reloaded) => $reloaded
                ->has('recentActivity', 1)
                ->where('recentActivity.0.description', 'User logged in from 127.0.0.1')));
});

test('dashboard metrics aggregate totals, series and merged queries', function () {
    $user = User::factory()->create(['name' => 'Metrics Owner']);

    $alpha = \App\Models\UserDatabase::create([
        'user_id' => $user->id,
        'team_id' => null,
        'database_name' => 'agg-alpha',
        'is_schema' => false,
        'created_by' => $user->id,
    ]);
    $beta = \App\Models\UserDatabase::create([
        'user_id' => $user->id,
        'team_id' => null,
        'database_name' => 'agg-beta',
        'is_schema' => false,
        'created_by' => $user->id,
    ]);

    $make = function (int $dbId, array $values, \Illuminate\Support\Carbon $at) {
        $metric = new \App\Models\QueryMetric();
        $metric->fill([
            'database_id' => $dbId,
            'rows_read_count' => $values['rows_read'],
            'rows_written_count' => $values['rows_written'],
            'storage_bytes_used' => $values['storage'],
            'write_requests_delegated' => 0,
            'replication_index' => 0,
            'embedded_replica_frames_replicated' => 0,
            'query_count' => $values['query_count'],
            'elapsed_ms' => $values['elapsed'],
            'queries' => null,
        ]);
        $metric->created_at = $at;
        $metric->updated_at = $at;
        $metric->save();

        return $metric;
    };

    $now = now();
    $older = $now->copy()->subHours(5);
    $previousWindow = $now->copy()->subHours(30);

    foreach ([$alpha->id, $beta->id] as $dbId) {
        $metric = $make($dbId, ['rows_read' => 100, 'rows_written' => 10, 'storage' => 1_000_000, 'query_count' => 5, 'elapsed' => 2.0], $older);
        \App\Models\TopQuery::create(['main_id' => $metric->id, 'query' => 'SELECT 1', 'rows_read' => 40, 'rows_written' => 1]);
        \App\Models\SlowestQuery::create(['main_id' => $metric->id, 'query' => 'SELECT pg_sleep(1)', 'elapsed_ms' => 400, 'rows_read' => 5, 'rows_written' => 0]);

        $metric = $make($dbId, ['rows_read' => 300, 'rows_written' => 30, 'storage' => 2_000_000, 'query_count' => 15, 'elapsed' => 6.0], $now);
        \App\Models\TopQuery::create(['main_id' => $metric->id, 'query' => 'SELECT 1', 'rows_read' => 60, 'rows_written' => 2]);
        \App\Models\SlowestQuery::create(['main_id' => $metric->id, 'query' => 'SELECT pg_sleep(1)', 'elapsed_ms' => 1200, 'rows_read' => 7, 'rows_written' => 0]);

        // Outside the 24h window — must land in the previous baseline only.
        $make($dbId, ['rows_read' => 9999, 'rows_written' => 999, 'storage' => 9_000_000, 'query_count' => 99, 'elapsed' => 99.0], $previousWindow);
    }

    $dashboard = \App\Models\QueryMetric::dashboard();

    // Team-wide totals exclude the previous window.
    expect($dashboard['totals']['query_count'])->toBe(40)
        ->and($dashboard['totals']['rows_read'])->toBe(800)
        ->and($dashboard['totals']['rows_written'])->toBe(80)
        ->and($dashboard['totals']['storage_bytes'])->toBe(4_000_000)
        ->and($dashboard['previous']['query_count'])->toBe(198);

    // 25 hourly buckets, ending at the current hour.
    expect($dashboard['series'])->toHaveCount(25)
        ->and($dashboard['series'][24]['t'])->toBe($now->copy()->format('Y-m-d H:00'));

    // Per-database entry carries its own previous baseline + samples count.
    expect($dashboard['databases'])->toHaveCount(2);
    $entry = collect($dashboard['databases'])->firstWhere('database_id', $alpha->id);
    expect($entry['samples'])->toBe(2)
        ->and($entry['query_count'])->toBe(20)
        ->and($entry['rows_read'])->toBe(400)
        ->and($entry['previous']['query_count'])->toBe(99);

    // Top queries merged across snapshots: rows summed, runs counted.
    $top = collect($dashboard['top_queries'])->firstWhere('query', 'SELECT 1');
    expect($top['rows_read'])->toBe(200)
        ->and($top['occurrences'])->toBe(4);

    // Slowest queries merged across snapshots: worst elapsed wins.
    $slowest = collect($dashboard['slowest_queries'])->firstWhere('query', 'SELECT pg_sleep(1)');
    expect($slowest['elapsed_ms'])->toBe(1200.0)
        ->and($slowest['occurrences'])->toBe(4);
});
