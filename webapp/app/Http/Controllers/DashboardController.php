<?php

namespace App\Http\Controllers;

use App\Models\QueryMetric;
use App\Models\UserActivityLog;
use App\Models\UserDatabase;
use App\Services\SqldService;
use App\Models\Team;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Keep the shared team/database session payload honest: dashboard
        // visits re-resolve it (preserving the team the user actually has
        // selected), so a database created/archived out-of-band never
        // renders as a phantom-empty world. The shared closures capture
        // the freshly written payload on subsequent renders.
        if (auth()->id()) {
            $teamId = Team::currentTeamDatabases()['team_id'] ?? null;
            Team::setTeamDatabases((int) auth()->id(), $teamId);
        }

        $databases = Team::currentTeamDatabases()['databases'] ?? SqldService::getDatabases(config('mylibsqladmin.local_instance'));

        // Deferred props: the expensive queries run only when the client
        // actually requests them (partial reload), not on every page hit.
        return Inertia::render('dashboard', [
            'databases' => $databases,
            'databaseMetrics' => Inertia::defer(fn() => QueryMetric::dashboard()),
            'mostUsedDatabases' => Inertia::defer(fn() => UserDatabase::mostUsedDatabases()),
            'recentActivity' => Inertia::defer(fn() => $this->recentActivity()),
        ]);
    }

    /**
     * Latest workspace events for the dashboard rail. Privileged roles
     * (Super Admin / Team Manager / manage-teams) see everything; everyone
     * else sees their own trail.
     */
    private function recentActivity(): array
    {
        $user = auth()->user();
        $canSeeAll = $user->hasRole('Super Admin')
            || $user->hasRole('Team Manager')
            || $user->hasPermission('manage-teams');

        return UserActivityLog::with('user:id,name')
            ->when(! $canSeeAll, fn($q) => $q->where('user_id', $user->id))
            ->orderByDesc('timestamp')
            ->limit(8)
            ->get()
            ->map(fn($log) => [
                'id' => $log->id,
                'type' => $log->type,
                'description' => $log->description,
                'user_name' => $log->user?->name,
                'time_ago' => $log->timestamp?->diffForHumans(),
            ])
            ->all();
    }
}
