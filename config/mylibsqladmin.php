<?php

return [

    'libsql' => [
        'connection' => [
            'host' => env('LIBSQL_HOST', 'db'),
            'port' => env('LIBSQL_PORT', '8080'),
        ],
        'api' => [
            'host' => env('LIBSQL_API_HOST', 'db'),
            'port' => env('LIBSQL_API_PORT', '8081'),
            'username' => env('LIBSQL_API_USERNAME', null),
            'password' => env('LIBSQL_API_PASSWORD', null),
        ]
    ],

    'local_instance' => env('LIBSQL_LOCAL_INSTANCE', true),
];
