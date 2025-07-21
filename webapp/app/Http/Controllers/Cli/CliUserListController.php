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

        return response()->json([
            'status' => 'success',
            'message' => 'Users fetched successfully',
            'data' => ['users' => $users]
        ]);
    }
}
