<?php

namespace App\Http\Controllers;

use App\ActivityType;
use App\Models\GroupDatabase;
use App\Models\GroupDatabaseToken;
use App\Models\Team;
use App\Models\UserDatabase;
use App\Services\DatabaseTokenGenerator;
use App\Services\SqldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class GroupDatabaseController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $teamId = Team::currentTeamDatabases()['team_id'] ?? null;

        if ($user->hasRole('Super Admin') || $user->hasRole('Team Manager') || $user->hasPermission('manage-teams') || $user->hasPermission('view-groups')) {
            $databaseGroups = GroupDatabase::databaseGroups($user->id, $teamId);
        } else {
            $databaseGroups = GroupDatabase::databaseGroups($user->id, null);
        }

        $databaseNotInGroup = UserDatabase::where('user_id', $user->id)
            ->whereDoesntHave('groups')
            ->get(['id', 'database_name', 'is_schema']);

        return Inertia::render('dashboard-group', [
            'databaseGroups' => $databaseGroups,
            'databaseNotInGroup' => $databaseNotInGroup
        ]);
    }

    public function createGroupToken(GroupDatabase $group, Request $request)
    {
        $validated = $request->validate([
            'group_id' => 'required|integer|exists:group_databases,id',
            'name' => 'required|string|max:255',
            'expiration' => 'required|integer|min:1|max:365'
        ]);

        $groupId = (int) $validated['group_id'];
        $inTeam = \DB::table('group_databases')
            ->join('team_user', 'team_user.team_id', '=', 'group_databases.team_id')
            ->where('group_databases.id', $groupId)
            ->where('team_user.user_id', $request->user()->id)
            ->exists();
        $canManage = $request->user()->hasPermission('manage-group-tokens')
            || $request->user()->hasPermission('manage-users');
        abort_unless($inTeam || $canManage, 403, 'Unauthorized group token creation');

        $pair = (new DatabaseTokenGenerator())->generateToken(
            $validated['name'],
            $validated['group_id'],
            $validated['expiration'],
            true
        );

        $groupToken = DB::transaction(fn() => $group->tokens()->updateOrCreate(
            [
                'group_id' => $validated['group_id'],
            ],
            [
                'name' => $validated['name'],
                'full_access_token' => hash('sha256', $pair['full_access_token']),
                'read_only_token' => hash('sha256', $pair['read_only_token']),
                'expiration_day' => $validated['expiration']
            ]
        ));

        // Plaintext exists only in this response; the database stores hashes.
        $groupToken->full_access_token = $pair['full_access_token'];
        $groupToken->read_only_token = $pair['read_only_token'];


        log_user_activity(
            auth()->user(),
            ActivityType::GROUP_DATABASE_TOKEN_CREATE,
            "Group token create from {$request->ip()}",
            activity_request_context()
        );

        return redirect()->back()->with([
            'success' => 'Group token created successfully',
            'newGroupToken' => $groupToken,
        ]);
    }

    public function deleteGroupToken($tokenId)
    {
        $token = GroupDatabaseToken::where('id', $tokenId)->first();
        abort_if(!$token, 404, 'Group token not found');

        $inTeam = DB::table('group_databases')
            ->join('team_user', 'team_user.team_id', '=', 'group_databases.team_id')
            ->where('group_databases.id', $token->group_id)
            ->where('team_user.user_id', auth()->id())
            ->exists();
        $canManage = auth()->user()->hasPermission('manage-group-tokens')
            || auth()->user()->hasPermission('manage-users');
        abort_unless($inTeam || $canManage, 403, 'Unauthorized');

        if (!$token) {
            return redirect()->back()->with([
                'error' => 'Group token not found',
            ]);
        }

        $token->delete();


        log_user_activity(
            auth()->user(),
            ActivityType::GROUP_DATABASE_TOKEN_DELETE,
            "Group token delete from " . request()->ip(),
            activity_request_context()
        );

        return redirect()->back()->with([
            'success' => 'Group token deleted successfully',
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
                    function ($attribute, $value, $fail) use ($request) {
                        if (GroupDatabase::where('name', $value)->where('team_id', $request->team_id)->exists()) {
                            $fail('Group name already exists.');
                        }
                    }
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

            // Authorization check - Fixed permission level name
            $team = Team::findOrFail($validated['team_id']);
            if (!$team->hasAccess(auth()->user(), ['database-maintainer'])) {
                abort(403, 'Unauthorized action');
            }

            $group = DB::transaction(function () use ($validated) {
                $group = GroupDatabase::create([
                    'name' => $validated['name'],
                    'user_id' => auth()->id(),
                    'team_id' => $validated['team_id'],
                    'created_by' => auth()->id() // Now properly saved
                ]);

                return $group->load(['team', 'user:id,name'])
                    ->loadCount('members');
            });

            Team::setTeamDatabases(auth()->id(), $validated['team_id']);


            log_user_activity(
                auth()->user(),
                ActivityType::GROUP_DATABASE_CREATE,
                "Group " . $validated['name'] . " create from " . request()->ip(),
                activity_request_context()
            );

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
                'message' => 'Group creation failed'
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

        Team::setTeamDatabases(auth()->id(), $validated['team_id']);


        log_user_activity(
            auth()->user(),
            ActivityType::GROUP_DATABASE_CREATE,
            "Group " . $validated['name'] . " create from " . request()->ip(),
            activity_request_context()
        );

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
            ->where('id', $groupId)
            ->firstOrFail();

        DB::transaction(function () use ($group) {
            $group->members()->sync([]);
            $group->tokens()->delete();
            $group->delete();
        });

        Team::setTeamDatabases(auth()->id(), $group->team_id);

        $databaseGroups = GroupDatabase::databaseGroups(auth()->id(), $group->team_id);

        $databaseNotInGroup = UserDatabase::where('user_id', auth()->id())
            ->whereDoesntHave('groups')
            ->get(['id', 'database_name']);


        log_user_activity(
            auth()->user(),
            ActivityType::GROUP_DATABASE_DELETE,
            "Group " . $group->name . " delete from " . request()->ip(),
            activity_request_context()
        );

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

        Team::setTeamDatabases(auth()->id(), $group->team_id);


        log_user_activity(
            auth()->user(),
            ActivityType::GROUP_DATABASE_UPDATE,
            "Group {$group->name} update from " . request()->ip(),
            activity_request_context()
        );

        return redirect()->back()->with([
            'success' => 'Databases added successfully'
        ]);
    }

    public function deleteDatabaseFromGroup(UserDatabase $database)
    {
        // "Remove from group" means detach the pivot row. It must never
        // archive/delete the database itself.
        $removed = DB::table('group_database_members')
            ->where('database_id', $database->id)
            ->delete();


        log_user_activity(
            auth()->user(),
            ActivityType::GROUP_DATABASE_UPDATE,
            "Database {$database->database_name} removed from group(s) from " . request()->ip(),
            array_merge(activity_request_context(request()), ['pivot_rows_removed' => $removed])
        );

        return redirect()->back()->with([
            'success' => 'Database removed from group successfully'
        ]);
    }
}
