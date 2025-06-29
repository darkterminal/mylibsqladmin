<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class CliUserListController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        // Get user identifier from header
        $userIdentifier = $request->header('X-User-Identifier');

        // Find requesting user
        $requestingUser = User::where('username', $userIdentifier)
            ->orWhere('id', $userIdentifier)->first();

        logger()->debug('Requesting user: ' . json_encode($requestingUser));

        if (!$requestingUser->exists()) {
            return response()->json(['error' => 'User not found'], 404);
        }

        logger()->debug("Role of requesting user is superadmin: " . $requestingUser->hasRole('Super Admin'));

        // Verify user is superadmin
        if (!$requestingUser->hasRole('Super Admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Get all users with their roles
        $users = User::with('roles:name')
            ->get()
            ->map(fn($user) => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->toArray(),
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
            ]);

        return response()->json(['users' => $users]);
    }
}
