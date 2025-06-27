<?php

use App\Http\Controllers\GroupDatabaseController;
use App\Http\Controllers\SubdomainValidationController;
use App\Http\Controllers\TeamController;
use App\Services\SqldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

Route::middleware('auth')->group(function () {
    Route::get('/api/databases', function () {
        try {
            $localDbs = SqldService::getDatabases(local: false);

            $transformed = array_map(fn($db) => [
                'name' => $db['database_name'],
                'status' => $db['deleted_at'] != null ? 'inactive' : 'active',
                'path' => $db['database_name'],
            ], $localDbs);

            return response()->json([
                'databases' => $transformed
            ]);
        } catch (Exception $e) {
            Log::error('Error in API /api/databases: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch databases',
            ], 500);
        }
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

Route::group(['prefix' => '/api/cli'], function () {
    Route::post('/login', function (Request $request) {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string'
        ]);

        $user = \App\Models\User::where('username', $validated['username'])->first();

        // Verify credentials
        if (!$user || !Hash::check((string) $validated['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Create token with 30-day expiration
        $token = $user->createToken(
            name: 'cli-auth-token',
            expiresAt: now()->addDays(30)
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at->toIso8601String(),
            'expires_in' => $token->accessToken->expires_at->diffInSeconds(now())
        ]);
    });

    Route::post('/logout', function (Request $request) {
        // Validate required token
        $request->validate([
            'token' => 'required|string'
        ]);

        // Find token without truncating (Sanctum stores tokens with hash)
        $token = PersonalAccessToken::findToken($request->token);

        if (!$token) {
            return response()->json([
                'message' => 'Invalid token'
            ], 401);
        }

        // Verify token hasn't expired
        if ($token->expires_at && now()->gt($token->expires_at)) {
            return response()->json([
                'message' => 'Token already expired'
            ], 401);
        }

        // Delete token
        $token->delete();

        return response()->json([
            'message' => 'Successfully logged out',
            'revoked_token' => $request->token
        ]);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/db/lists', function (Request $request) {
            $userIdentifier = $request->header('X-User-Identifier');

            // Find user by ID or username
            $user = is_numeric($userIdentifier)
                ? \App\Models\User::find($userIdentifier)
                : \App\Models\User::where('username', $userIdentifier)->first();

            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            $databases = \App\Models\UserDatabase::where('user_id', $user->id)
                ->orWhereHas('groups.members', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->with([
                    'groups' => function ($query) {
                        $query->select('id', 'name');
                    }
                ])
                ->get()
                ->map(fn($db) => [
                    'database_name' => $db->database_name,
                    'is_schema' => is_numeric($db->is_schema) && (int) $db->is_schema === 1,
                    'group_name' => $db->groups->first()?->name ?? 'Personal',
                    'owner' => $userIdentifier,
                    'created_at' => $db->created_at->format('Y-m-d H:i:s'),
                ]);

            return response()->json([
                'user_identifier' => $userIdentifier,
                'databases' => $databases
            ], 200);
        });
    });
});

Route::get('/validate-subdomain', [SubdomainValidationController::class, 'validateSubdomain']);
