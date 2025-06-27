<?php

$dbPath = getenv('HOME') . DIRECTORY_SEPARATOR . '.mylibsqladmin' . DIRECTORY_SEPARATOR . 'tokens.db';
const TABLE_NAME = 'auth_tokens';

return [
    'db_path' => $dbPath,
    'table_name' => TABLE_NAME
];
