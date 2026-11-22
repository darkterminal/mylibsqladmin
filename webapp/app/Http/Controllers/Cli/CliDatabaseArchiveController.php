<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Models\UserDatabase;
use Illuminate\Http\Request;

class CliDatabaseArchiveController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        // Identity comes from the Sanctum session, not from client headers.
        $user = $request->user();

        // onlyTrashed() must sit OUTSIDE the grouped owner/grant condition.
        // With a bare ->orWhereHas() the OR escaped the soft-delete clause and
        // live databases shared with the caller leaked into the archive list.
        $databases = UserDatabase::onlyTrashed()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('grant', function ($query) use ($user) {
                        $query->where('user_id', $user->id);
                    });
            })
            ->with([
                'groups' => function ($query) {
                    $query->select('id', 'name');
                },
                'user:id,name,username',
            ])
            ->get()
            ->map(fn($db) => [
                'database_name' => $db->database_name,
                'is_schema' => $db->is_schema,
                'group_name' => $db->groups->first()?->name ?? 'Personal',
                'owner' => $db->user?->username ?? 'unknown',
                'created_at' => $db->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $db->updated_at->format('Y-m-d H:i:s'),
                'deleted_at' => $db->deleted_at,
            ])
            ->filter(fn($db) => $db['deleted_at'] !== null);

        return response()->json([
            'status' => 'success',
            'message' => 'Databases fetched successfully',
            'data' => [
                'databases' => $databases
            ]
        ], 200);
    }
}
