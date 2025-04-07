<?php

namespace App\Http\Controllers;

use App\Models\GroupDatabase;
use App\Models\GroupDatabaseToken;
use App\Models\Team;
use App\Models\UserDatabase;
use App\Services\DatabaseTokenGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GroupController extends Controller
{
    public function createGroupToken(GroupDatabase $group, Request $request)
    {
        $validated = $request->validate([
            'group_id' => 'required|integer|exists:group_databases,id',
            'name' => 'required|string|max:255',
            'expiration' => 'required|integer|min:1|max:365'
        ]);

        $tokenGenerator = (new DatabaseTokenGenerator())->generateToken(
            $validated['name'],
            $validated['group_id'],
            $validated['expiration'],
            true
        );

        DB::transaction(function () use ($group, $validated, $tokenGenerator) {
            return $group->tokens()->updateOrCreate(
                [
                    'group_id' => $validated['group_id'],
                ],
                [
                    'name' => $validated['name'],
                    'full_access_token' => $tokenGenerator['full_access_token'],
                    'read_only_token' => $tokenGenerator['read_only_token'],
                    'expiration_day' => $validated['expiration']
                ]
            );
        });

        return redirect()->back()->with([
            'success' => 'Group token created successfully'
        ]);
    }

    public function deleteGroupToken($tokenId)
    {
        GroupDatabaseToken::where('id', $tokenId)->delete();
        return redirect()->back()->with([
            'success' => 'Group token deleted successfully'
        ]);
    }

    public function createGroupOnly(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('group_databases')->where(function ($query) use ($request) {
                        return $query->where('team_id', $request->team_id);
                    })
                ],
                'team_id' => [
                    'required',
                    'integer',
                    'exists:teams,id',
                    function ($attribute, $value, $fail) {
                        if (!auth()->user()->teams()->where('team_id', $value)->exists()) {
                            $fail('You are not a member of this team.');
                        }
                    }
                ]
            ]);

            // Authorization check
            $team = Team::findOrFail($validated['team_id']);
            if (!$team->hasAccess(auth()->user(), ['maintainer'])) {
                abort(403, 'Unauthorized action');
            }

            $group = DB::transaction(function () use ($validated) {
                $group = GroupDatabase::create([
                    'name' => $validated['name'],
                    'user_id' => auth()->id(),
                    'team_id' => $validated['team_id'],
                ]);

                return $group->load(['team', 'user:id,name'])
                    ->loadCount('members');
            });

            return response()->json([
                'success' => true,
                'message' => 'Group created successfully',
                'group' => $group
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Group creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function createGroup(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'team_id' => 'required|integer|exists:teams,id',
            'databases' => 'required|array|min:1',
            'databases.*' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    if (
                        !UserDatabase::where('id', $value)
                            ->where('user_id', auth()->id())
                            ->exists()
                    ) {
                        $fail('Invalid database selected.');
                    }
                },
            ],
        ]);

        DB::transaction(function () use ($validated) {
            $group = GroupDatabase::create([
                'name' => $validated['name'],
                'user_id' => auth()->id(),
                'team_id' => $validated['team_id'],
            ]);

            $group->members()->sync($validated['databases']);

            return $group->load(['members', 'tokens', 'user'])
                ->loadCount('members');
        });

        $databaseGroups = GroupDatabase::databaseGroups(auth()->id(), $validated['team_id']);

        $databaseNotInGroup = UserDatabase::where('user_id', auth()->id())
            ->whereDoesntHave('groups')
            ->get(['id', 'database_name']);

        return redirect()->back()->with([
            'success' => 'Group created successfully',
            'databaseGroups' => $databaseGroups,
            'databaseNotInGroup' => $databaseNotInGroup
        ]);
    }

    public function deleteGroup($groupId)
    {
        $group = GroupDatabase::with(['members', 'tokens'])
            ->where('user_id', auth()->id())
            ->findOrFail($groupId);

        DB::transaction(function () use ($group) {
            $group->members()->detach();
            $group->tokens()->delete();
            $group->delete();
        });

        $databaseGroups = GroupDatabase::databaseGroups(auth()->id(), $group->team_id);

        $databaseNotInGroup = UserDatabase::where('user_id', auth()->id())
            ->whereDoesntHave('groups')
            ->get(['id', 'database_name']);

        return redirect()->back()->with([
            'success' => 'Group deleted successfully',
            'databaseGroups' => $databaseGroups,
            'databaseNotInGroup' => $databaseNotInGroup
        ]);
    }

    public function addDatabasesToGroup(GroupDatabase $group, Request $request)
    {
        $validated = $request->validate([
            'databases' => 'required|array|min:1',
            'databases.*' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($group) {
                    $exists = UserDatabase::where('id', $value)
                        ->where('user_id', auth()->id())
                        ->whereDoesntHave('groups', fn($q) => $q->where('group_id', $group->id))
                        ->exists();

                    if (!$exists) {
                        $fail('Invalid database selected.');
                    }
                },
            ],
        ]);

        DB::transaction(function () use ($group, $validated) {
            $group->members()->syncWithoutDetaching($validated['databases']);
        });

        return redirect()->back()->with([
            'success' => 'Databases added successfully'
        ]);
    }

    public function deleteDatabaseFromGroup(GroupDatabase $group, UserDatabase $database)
    {
        $group->members()->detach($database);

        return redirect()->back()->with([
            'success' => 'Database removed successfully'
        ]);
    }
}
