<?php

$config = [
    "url" => "file:./database.db",
    "authToken" => getenv("FAT_AUTH_TOKEN"),
    "syncUrl" => "http://db-testing.localhost:8080",
];

$db = new LibSQL($config);
$db->sync();

$db->execute("INSERT INTO users VALUES ('test11')");

$data = $db->query("SELECT * FROM users");
$result = $data->fetchArray(LibSQL::LIBSQL_ASSOC);
print_r($result);

$db->sync();
