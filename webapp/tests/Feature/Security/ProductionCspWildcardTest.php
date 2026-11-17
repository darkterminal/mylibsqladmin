<?php

/**
 * Production CSP must allow the app's own parent domain (single level) for
 * Database Studio's sqld endpoints — and must NOT allow anything for
 * multi-part public suffixes or localhost/IP hosts.
 */

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('production CSP allows the parent-domain wildcard for simple TLDs', function () {
    $this->app->detectEnvironment(fn () => 'production');
    config(['app.url' => 'https://app.example.dev']);

    $response = $this->get('/login');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain('connect-src')
        ->toContain('https://*.example.dev');
});

test('production CSP allows the wildcard for root domains with simple TLDs', function () {
    $this->app->detectEnvironment(fn () => 'production');
    config(['app.url' => 'https://example.com']);

    $response = $this->get('/login');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain('https://*.example.com');
});

test('production CSP refuses the wildcard for multi-part public suffixes', function () {
    $this->app->detectEnvironment(fn () => 'production');
    config(['app.url' => 'https://app.example.co.uk']);

    $response = $this->get('/login');

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->not->toContain('.co.uk')
        ->and($csp)->toContain("connect-src 'self'");
});

test('production CSP refuses the wildcard for localhost', function () {
    $this->app->detectEnvironment(fn () => 'production');
    config(['app.url' => 'http://localhost:8000']);

    $response = $this->get('/login');

    expect($response->headers->get('Content-Security-Policy'))->not->toContain('.localhost');
});

test('production CSP refuses the wildcard for IP addresses', function () {
    $this->app->detectEnvironment(fn () => 'production');
    config(['app.url' => 'http://192.168.1.10:8000']);

    $response = $this->get('/login');

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->not->toContain('*.')
        ->and($csp)->not->toContain('192.168.1.10');
});
