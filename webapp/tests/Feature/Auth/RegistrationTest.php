<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('registration is disabled by default and redirects to login', function () {
    $response = $this->get('/register');

    $response->assertRedirect('/login');
});

test('registration screen can be rendered when enabled', function () {
    config()->set('app.registration_enabled', true);

    $response = $this->get('/register');

    $response->assertOk();
});

test('new users can register when enabled', function () {
    config()->set('app.registration_enabled', true);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
