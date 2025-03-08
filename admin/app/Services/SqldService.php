<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDatabase;
use Illuminate\Support\Facades\Http;

class SqldService
{
    public static function getDatabases(): array
    {
        $databases = Http::withHeaders([
            'Authorization' => 'realm=' . env('BRIDGE_HTTP_PASSWORD', 'libsql'),
            'Content-Type' => 'application/json',
        ])
            ->get('http://bridge:4500/api/databases')
            ->collect()
            ->toArray();

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
        if (is_bool($isSchema)) {
            $data['shared_schema'] = $isSchema;
        } else {
            $data['shared_schema_name'] = $isSchema;
        }

        $request = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])
            ->post("http://db:8081/v1/namespaces/$database/create", $data);

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
        $request = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])
            ->delete("http://db:8081/v1/namespaces/$database");

        if ($request->failed()) {
            return false;
        }

        UserDatabase::where('database_name', $database)
            ->where('user_id', auth()->user()->id)
            ->delete();

        return true;
    }

    public static function deleteDatabaseExcept(string $database): void
    {
        $databases = self::getDatabases();
        foreach ($databases as $db) {
            if ($db['name'] === $database) {
                Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])
                    ->delete('http://db:8081/v1/namespaces/' . $db['name']);

                UserDatabase::where('database_name', $database)
                    ->where('user_id', auth()->user()->id)
                    ->delete();
            }
        }
    }
}
