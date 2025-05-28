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
];
