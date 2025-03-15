<?php

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

    $userAgent = $request->header('User-Agent');

    if ($userAgent !== "Go-http-client/1.1") {
        return response('Valid', 200);
    }

    $token = Str::startsWith($authToken, 'Bearer ')
        ? Str::after($authToken, 'Bearer ')
        : $authToken;

    if (!$token) {
        return response("Missing token", 403);
    }

    $userToken = UserDatabaseToken::where('token', $token)->first();
    if (!$userToken) {
        return response("Invalid token", 403);
    }

    $response = Http::withHeaders([
        'Authorization' => 'realm=' . env('BRIDGE_HTTP_PASSWORD', 'libsql'),
        'Content-Type' => 'application/json',
    ])
        ->get('http://bridge:4500/api/databases');

    if ($response->successful()) {
        $namespaces = array_map(fn($db) => $db['name'], $response->json());
        if (in_array($subdomain, $namespaces)) {
            return response('Valid', 200);
        }
    }

    return response("Invalid database $subdomain", 403);
});
