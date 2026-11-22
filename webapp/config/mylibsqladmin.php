<?php

return [

    'libsql' => [
        'connection' => [
            'host' => mylibsqladmin_env('LIBSQL_HOST'),
            'port' => env('LIBSQL_PORT', '8080'),
        ],
        'api' => [
            'host' => mylibsqladmin_env('LIBSQL_API_HOST'),
            'port' => env('LIBSQL_API_PORT', '8081'),
            'username' => env('LIBSQL_API_USERNAME', null),
            'password' => env('LIBSQL_API_PASSWORD', null),
        ]
    ],

    'local_instance' => env('LIBSQL_LOCAL_INSTANCE', true),

    /**
     * Client-facing parent domain used by the nginx proxy to derive the
     * per-database subdomain and by CSP to allow Database Studio endpoints.
     * Example: example.com (or example.co.uk). Empty keeps legacy behaviour.
     */
    'parent_domain' => env('LIBSQL_PARENT_DOMAIN'),

    /**
     * Display-only timezone. All data is stored in UTC regardless of this
     * value; it is used solely for rendering dates and times for the user.
     * Can be overridden at runtime by a Super Admin via /settings/general.
     */
    'display' => [
        'timezone' => env('APP_TIMEZONE', 'UTC'),
    ],
];
