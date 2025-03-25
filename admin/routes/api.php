<?php

use App\Models\GroupDatabase;
use App\Models\UserDatabase;
use App\Models\UserDatabaseToken;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;

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
    $parser = new Parser(new JoseEncoder());

    $token = Str::startsWith($authToken, 'Bearer ')
        ? Str::after($authToken, 'Bearer ')
        : $authToken;

    if (empty($token)) {
        $groupToken = GroupDatabase::with(['members', 'tokens'])
            ->whereHas('members', function ($query) use ($subdomain) {
                $query->where('database_name', $subdomain);
            });

        $databaseToken = UserDatabaseToken::with('database')
            ->whereHas('database', function ($query) use ($subdomain) {
                $query->where('database_name', $subdomain);
            });

        if (!$databaseToken->exists()) {
            return response(null, 200)->header('X-Access-Level', 'full-access');
        }

        if (!$groupToken->exists()) {
            return response(null, 200)->header('X-Access-Level', 'full-access');
        }

        return response(null, 200)->header('X-Access-Level', 'none');
    }

    try {
        $validateToken = $parser->parse($token);
    } catch (CannotDecodeContent | InvalidTokenStructure | UnsupportedHeaderFound $e) {
        logger('Oh no, an error: ' . $e->getMessage());
        return response(null, 200)->header('X-Access-Level', 'none');
    }

    $headers = $validateToken->headers();
    $claims = $validateToken->claims();

    logger('DEBUG: ', [
        'headers' => $headers,
        'claims' => $claims,
    ]);

    if ($validateToken->isExpired(now(new DateTimeZone(env('APP_TIMEZONE', 'UTC'))))) {
        return response(null, 200)->header('X-Access-Level', 'none');
    }

    if ($headers->get('is_group') === 'yes' && $claims->get('uid') === 'none') {
        $group_id = $claims->get('gid');
        $group = GroupDatabase::getGroupDatabasesIfContains($group_id, $subdomain);

        if (!$group) {
            return response(null, 200)->header('X-Access-Level', 'none');
        }

        if ($group->tokens()->first()->full_access_token === $token) {
            $accessLevel = 'full-access';
        }
    } else {
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
    }

    logger("DEBUG ACCESS LEVEL: $accessLevel");

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
