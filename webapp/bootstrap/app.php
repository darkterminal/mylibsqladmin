<?php

use App\Http\Middleware\CheckInvitationExpiration;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureCliRequest;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\SecureHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            '/api/cli/*'
        ]);

        // Cloudflare defaults plus optional TRUST_PRIVATE_PROXIES /
        // TRUSTED_PROXIES. Resolved in one place; no runtime source mutation.
        $middleware->trustProxies(\App\Support\TrustedProxies::resolve(
            filter_var(env('TRUST_PRIVATE_PROXIES', false), FILTER_VALIDATE_BOOLEAN),
            env('TRUSTED_PROXIES'),
        ));

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureActiveUser::class,
            SecureHeaders::class,
        ]);

        $middleware->alias([
            'permission' => CheckPermission::class,
            'invitationExpiration' => CheckInvitationExpiration::class,
            'ensureCliRequest' => EnsureCliRequest::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
