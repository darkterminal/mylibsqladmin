<?php

const DATABASE_DIR = __DIR__ . '/data/libsql/data.sqld/dbs';
$verify = $_ENV['BRIDGE_HTTP_PASSWORD'] ?? 'libsql';

if (php_sapi_name() === 'cli-server') {
    $url = parse_url($_SERVER['REQUEST_URI']);
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $password = substr($auth, 6);

    if ($url['path'] === '/health') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
        exit;
    }

    if ($password !== $verify) {
        header('HTTP/1.0 401 Unauthorized');
        exit;
    }

    if ($url['path'] === '/api/databases') {
        header('Content-Type: application/json');

        if (!is_dir(DATABASE_DIR)) {
            http_response_code(500);
            echo json_encode(['error' => 'Database directory not found']);
            exit;
        }

        $directories = scandir(DATABASE_DIR);
        if ($directories === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to read database directory']);
            exit;
        }

        $directories = array_values(array_diff($directories, ['..', '.']));
        $databases = array_map(function ($dir) {
            return [
                'name' => $dir
            ];
        }, $directories);

        echo json_encode($databases);
        exit;
    }
}
