<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SqldService
{
    public static function getDatabases(): array
    {
        return Http::withHeaders([
            'Authorization' => 'realm=' . env('BRIDGE_HTTP_PASSWORD', 'libsql'),
            'Content-Type' => 'application/json',
        ])
            ->get('http://bridge:4500/api/databases')
            ->collect()->toArray();
    }
}
