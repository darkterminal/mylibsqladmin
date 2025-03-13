<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDatabase;
use Illuminate\Support\Facades\Http;

class SqldService
{
    private static function isRunningInDocker(): bool
    {
        // Check for standard Docker indicators
        return file_exists('/.dockerenv') ||
            (is_readable('/proc/self/cgroup') &&
                strpos(file_get_contents('/proc/self/cgroup'), 'docker') !== false);
    }

    public static function useEndpoint(string $service): string|false
    {
        $isDocker = self::isRunningInDocker();

        switch ($service) {
            case 'db':
                return $isDocker ? 'http://db:8081' : 'http://localhost:8081';
            case 'bridge':
                return $isDocker ? 'http://bridge:4500' : 'http://localhost:4500';
            default:
                throw new \BadMethodCallException("Unknown endpoint", 1);
        }
    }

    public static function getDatabases(): array
    {
        $host = self::useEndpoint('bridge');
        $databases = Http::retry(5, 100)->withHeaders([
            'Authorization' => 'realm=' . env('BRIDGE_HTTP_PASSWORD', 'libsql'),
            'Content-Type' => 'application/json',
        ])
            ->get("$host/api/databases")
            ->collect()
            ->toArray();

        if (php_sapi_name() === 'cli') {
            $allDatabases = [];
            foreach ($databases as $database) {
                if ($database['name'] !== 'default') {
                    $userDatabase = UserDatabase::where('database_name', $database['name'])->first();
                    if ($userDatabase) {
                        $allDatabases[] = $userDatabase->toArray();
                    }
                }
            }
            return $allDatabases;
        }

        if (!auth()->check()) {
            return [];
        }

        $userId = auth()->user()->id;

        foreach ($databases as $database) {
            if ($database['name'] !== 'default') {
                UserDatabase::updateOrInsert(
                    ['user_id' => $userId, 'database_name' => $database['name']],
                    ['user_id' => $userId, 'database_name' => $database['name']]
                );
            }
        }

        return UserDatabase::where('user_id', $userId)->get()->toArray();

    }

    public static function createDatabase(string $database, mixed $isSchema): bool
    {
        $host = self::useEndpoint('db');
        if (is_bool($isSchema)) {
            $data['shared_schema'] = $isSchema;
        } else {
            $data['shared_schema_name'] = $isSchema;
        }

        $request = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])
            ->post("$host/v1/namespaces/$database/create", $data);

        if ($request->failed()) {
            return false;
        }

        UserDatabase::create([
            'user_id' => auth()->user()->id,
            'database_name' => $database,
            'is_schema' => $isSchema
        ]);

        return true;
    }

    public static function deleteDatabase(string $database): bool
    {
        $host = self::useEndpoint('db');
        $request = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])
            ->delete("$host/v1/namespaces/$database");

        if ($request->getStatusCode() !== 200) {
            return false;
        }

        UserDatabase::where('database_name', $database)
            ->where('user_id', auth()->user()->id)
            ->delete();

        return true;
    }

    public static function deleteDatabaseExcept(string $database): void
    {
        $host = self::useEndpoint('db');
        $databases = self::getDatabases();

        foreach ($databases as $db) {
            if ($db['database_name'] === $database) {
                Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])
                    ->delete("$host/v1/namespaces/" . $db['database_name']);

                UserDatabase::where('database_name', $database)
                    ->where('user_id', auth()->user()->id)
                    ->delete();
            }
        }
    }
}
