<?php

$dbPath = getenv('HOME') . DIRECTORY_SEPARATOR . '.mylibsqladmin' . DIRECTORY_SEPARATOR . 'libsql3.db';
const TOKEN_TABLE = 'auth_tokens';

return [
    'db_path' => $dbPath,
    'token_table' => TOKEN_TABLE
];
