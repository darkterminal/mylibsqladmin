<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDatabase;
use Illuminate\Http\Request;

class CliDatabaseListController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $userIdentifier = $request->header('X-User-Identifier');

        // Find user by ID or username
        $user = is_numeric($userIdentifier)
            ? User::find($userIdentifier)
            : User::where('username', $userIdentifier)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $databases = UserDatabase::where('user_id', $user->id)
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
    }
}
