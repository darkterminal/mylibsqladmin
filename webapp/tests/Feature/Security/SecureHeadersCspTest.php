<?php

/**
 * CSP must stay strict in production AND must not break the Vite dev
 * server in non-production (regression guard for the blank-page bug).
 */

use Illuminate\Support\Facades\File;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('csp allows the vite dev server origin in non-production environments', function () {
    $hot = public_path('hot');
    $backup = file_exists($hot) ? file_get_contents($hot) : null;

    File::put($hot, "http://127.0.0.1:5175\n");

    try {
        $response = $this->get('/login');

        $csp = $response->headers->get('Content-Security-Policy');

        expect($csp)->toContain('script-src')
            ->and($csp)->toContain('http://127.0.0.1:5175')
            ->and($csp)->toContain("connect-src")
            ->and($csp)->toContain('ws://127.0.0.1:5175');
    } finally {
        $backup !== null ? file_put_contents($hot, $backup) : File::delete($hot);
    }
});

test('csp excludes the dev server origin when no hot file is present', function () {
    $hot = public_path('hot');
    $backup = file_exists($hot) ? file_get_contents($hot) : null;

    File::delete($hot);

    try {
        $response = $this->get('/login');

        $csp = $response->headers->get('Content-Security-Policy');

        expect($csp)->not->toContain('127.0.0.1:5175')
            ->and($csp)->toContain("default-src 'self'");
    } finally {
        $backup !== null ? file_put_contents($hot, $backup) : null;
    }
});
