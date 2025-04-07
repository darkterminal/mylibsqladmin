<?php

namespace App\Http\Controllers;

use App\Models\GroupDatabase;
use App\Models\UserDatabaseToken;
use App\Services\DatabaseTokenGenerator;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function createToken(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'databaseId' => 'required|integer',
            'expiration' => 'required|integer',
        ]);

        $tokenGenerator = (new DatabaseTokenGenerator())->generateToken(
            $validated['databaseId'],
            auth()->id(),
            $validated['expiration']
        );

        if (!$tokenGenerator) {
            return redirect()->back()
                ->with('error', 'Failed to generate tokens');
        }

        $formData = [
            'user_id' => auth()->id(),
            'database_id' => $validated['databaseId'],
            'name' => $validated['name'],
            'full_access_token' => $tokenGenerator['full_access_token'],
            'read_only_token' => $tokenGenerator['read_only_token'],
            'expiration_day' => $validated['expiration'],
        ];

        try {
            UserDatabaseToken::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'database_id' => $validated['databaseId'],
                    'name' => $validated['name'],
                    'full_access_token' => $tokenGenerator['full_access_token'],
                    'read_only_token' => $tokenGenerator['read_only_token'],
                    'expiration_day' => $validated['expiration'],
                ],
                $formData
            );

            return redirect()->back()->with([
                'success' => 'Token created/updated successfully',
                'newToken' => UserDatabaseToken::latest()->first(),
                'databaseGroups' => GroupDatabase::databaseGroups(auth()->id(), session('team_databases')['team_id'] ?? null),
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to save token: ' . $e->getMessage());
        }
    }

    public function deleteToken(int $tokenId)
    {
        UserDatabaseToken::where('id', $tokenId)->delete();
        return redirect()->back()->with([
            'success' => 'Token deleted successfully',
            'userDatabaseTokens' => UserDatabaseToken::where('user_id', auth()->id())->get()
        ]);
    }
}
