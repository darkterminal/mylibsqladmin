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
