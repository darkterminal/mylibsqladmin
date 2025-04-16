<?php

return [
    'libsql' => [
        'host' => env('LIBSQL_API_HOST', 'db'),
        'port' => env('LIBSQL_API_PORT', '8081'),
        'username' => env('LIBSQL_API_USERNAME', null),
        'password' => env('LIBSQL_API_PASSWORD', null),
    ],

    'bridge' => [
        'host' => env('BRIDGE_HTTP_HOST', 'bridge'),
        'port' => env('BRIDGE_HTTP_PORT', '4500'),
        'password' => env('BRIDGE_HTTP_PASSWORD', 'libsql'),
    ]
];
