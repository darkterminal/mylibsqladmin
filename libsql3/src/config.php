<?php

$dbPath = getenv('HOME') . DIRECTORY_SEPARATOR . '.mylibsqladmin' . DIRECTORY_SEPARATOR . 'libsql3.db';

return [
    'api_endpoint' => getenv('LIBSQL_API_ENDPOINT'),
    'db_path' => $dbPath,
    'tables' => [
        'tokens' => 'auth_tokens',
        'config' => 'configuration'
    ]
];
