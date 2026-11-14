<?php

$config = [
    "url" => "file:./database.db",
    "authToken" => getenv('LIBSQL_AUTH_TOKEN') ?: die('Set LIBSQL_AUTH_TOKEN in the environment first.'),
    "syncUrl" => getenv('LIBSQL_SYNC_URL') ?: 'http://db-testing.localhost:8080',
];

$db = new LibSQL($config);
$db->sync();

// $db->execute("INSERT INTO users (name) VALUES ('test5')");

$data = $db->query("SELECT * FROM users");
$result = $data->fetchArray(LibSQL::LIBSQL_ASSOC);
print_r($result);

$db->sync();
