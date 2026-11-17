<?php

namespace App\Http\Controllers;

use App\Models\GrantedDatabase;
use App\Models\UserDatabase;
use App\Models\UserDatabaseToken;
use App\Services\DatabaseTokenGenerator;
use Illuminate\Http\Request;

class StudioTokenController extends Controller
{
    /**
     * Issue (or refresh) a Studio full-access token for a database the
     * current user is allowed to open. Opening the Database Studio always
     * mints a fresh token — so opening always yields a usable plaintext
     * token for THIS response only. The previous Studio token, if any, is
     * replaced.
     */
    public function issue(Request $request)
    {
        $validated = $request->validate([
            'database' => 'required|string|exists:user_databases,database_name',
        ]);

        $user = $request->user();
        $database = UserDatabase::where('database_name', $validated['database'])->firstOrFail();

        if (!$user->hasRole('Super Admin') && !$user->hasRole('Team Manager')) {
            $granted = GrantedDatabase::where('user_id', $user->id)
                ->where('database_id', $database->id)
                ->exists();

            abort_unless($granted, 403);
        }

        $pair = (new DatabaseTokenGenerator())->generateToken($database->id, $user->id, 0);

        if (!$pair) {
            return response()->json([
                'success' => false,
                'message' => 'Token could not be created',
            ], 500);
        }

        $formData = [
            'user_id' => $user->id,
            'database_id' => $database->id,
            'name' => 'Studio — ' . $database->database_name,
            'full_access_token' => hash('sha256', $pair['full_access_token']),
            'read_only_token' => hash('sha256', $pair['read_only_token']),
            'expiration_day' => 0,
            'created_by' => $user->id,
        ];

        UserDatabaseToken::updateOrCreate(
            ['user_id' => $user->id, 'database_id' => $database->id],
            $formData
        );

        return response()->json([
            'success' => true,
            'full_access_token' => $pair['full_access_token'],
        ]);
    }
}
