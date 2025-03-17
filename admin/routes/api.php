<?php

use App\Models\UserDatabase;
use App\Models\UserDatabaseToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

Route::middleware('auth')->group(function () {
    Route::get('/api/databases', function () {
        $response = Http::withHeaders([
            'Authorization' => 'realm=' . env('BRIDGE_HTTP_PASSWORD', 'libsql'),
            'Content-Type' => 'application/json',
        ])
            ->get('http://bridge:4500/api/databases');

        if ($response->successful()) {
            return response()->json([
                'databases' => $response->json()
            ]);
        }

        return response()->json([
            'error' => 'Failed to fetch databases',
            'details' => $response->body()
        ], $response->status());
    });
});

Route::get('/validate-subdomain', function (Request $request) {
    $subdomain = $request->header('X-Subdomain');
    $authToken = $request->header('X-Auth-Token');
    $accessLevel = 'read-only';

    $token = Str::startsWith($authToken, 'Bearer ')
        ? Str::after($authToken, 'Bearer ')
        : $authToken;

    $databaseToken = UserDatabaseToken::with('database')
        ->whereHas('database', function ($query) use ($subdomain) {
            $query->where('database_name', $subdomain);
        })->first();

    if (empty($token) && !empty($databaseToken)) {
        return response(null, 200)->header('X-Access-Level', 'none');
    }

    if ($databaseToken && $databaseToken->full_access_token === $token) {
        $accessLevel = 'full-access';
    }

    $response = Http::withHeaders([
        'Authorization' => 'realm=' . env('BRIDGE_HTTP_PASSWORD', 'libsql'),
        'Content-Type' => 'application/json',
    ])
        ->get('http://bridge:4500/api/databases');

    if ($response->successful()) {
        $namespaces = array_map(fn($db) => $db['name'], $response->json());
        if (in_array($subdomain, $namespaces)) {
            return response(null, 200)
                ->header('X-Access-Level', $accessLevel);
        }
    }

    return response(null, 403)->header('X-Access-Level', $accessLevel);
});
