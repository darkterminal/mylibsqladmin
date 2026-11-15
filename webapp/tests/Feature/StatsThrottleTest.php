<?php

/**
 * PERF-03 regression guard — the stats fetch (remote HTTP + metric inserts)
 * must not execute unthrottled inside the Studio statement request cycle.
 *
 * Behaviour pinned here: when a recent fetch marker exists in the cache,
 * the trigger route returns 200 immediately and performs no network call.
 */

use App\Models\User;
use Illuminate\Support\Facades\Cache;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('stats trigger returns 200 immediately when throttled', function () {
    $db = 'throttle-test-' . uniqid();

    Cache::put('stats-throttle:db:' . md5($db), true, 30);

    $this->actingAs(User::factory()->create())
        ->get("/trigger-stats-changed/{$db}/web")
        ->assertOk()
        ->assertSee("Stats changed for {$db}", false);
});

test('throttled stats fetch produces no metric rows', function () {
    $db = 'throttle-test-' . uniqid();

    Cache::put('stats-throttle:db:' . md5($db), true, 30);

    $this->actingAs(User::factory()->create())
        ->get("/trigger-stats-changed/{$db}/web")
        ->assertOk();

    expect(\App\Models\QueryMetric::count())->toBe(0);
});
