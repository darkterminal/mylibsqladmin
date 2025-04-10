<?php

use App\Http\Controllers\GroupDatabaseController;
use App\Http\Controllers\SubdomainValidationController;
use App\Http\Controllers\TeamController;
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

    Route::post('/api/group/create-only', [GroupDatabaseController::class, 'createGroupOnly'])->name('api.group.create-only');
    Route::get('/api/teams/{teamId}/databases', [TeamController::class, 'getDatabases'])->name('api.teams.databases');
    Route::post('/api/check-gate', function (Request $request) {
        $model = $request->model_type::findOrFail($request->model_id);

        return response()->json([
            'allowed' => Gate::allows($request->ability, $model)
        ]);
    })->name('api.check-gate');
});

Route::get('/validate-subdomain', [SubdomainValidationController::class, 'validateSubdomain']);
