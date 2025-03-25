<?php

$config = [
    "url" => "file:./database.db",
    "authToken" => "eyJ0eXAiOiJKV1QiLCJhbGciOiJFZERTQSIsImlzX2dyb3VwIjoieWVzIn0.eyJpYXQiOjE3NDI0ODc5MTYsIm5iZiI6MTc0MjQ4NzkxNiwiZXhwIjoxNzQ1MDc5OTE2LCJqdGkiOiJ0ZXN0aW5nIGdyb3VwIHRva2VuIiwiaWQiOiJ0ZXN0aW5nIGdyb3VwIHRva2VuIiwidWlkIjoibm9uZSIsImdpZCI6MX0.2mtRl1307LQaASJBmtJ9GjLQCNYG2MDA5G_-xuhzFJ9rLxiVbV4viVLjxjx7imEyLMp62uwwnbyYPTWWfCnVAA",
    "syncUrl" => "http://db-testing.localhost:8080",
];

$db = new LibSQL($config);
$db->sync();

// $db->execute("INSERT INTO users (name) VALUES ('test5')");

$data = $db->query("SELECT * FROM users");
$result = $data->fetchArray(LibSQL::LIBSQL_ASSOC);
print_r($result);

$db->sync();
