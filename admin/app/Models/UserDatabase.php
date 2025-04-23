<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class UserDatabase extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'team_id',
        'database_name',
        'is_schema',
        'created_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function queryMetrics()
    {
        return $this->hasMany(QueryMetric::class, 'database_id');
    }

    public function tokens()
    {
        return $this->hasMany(UserDatabaseToken::class, 'database_id');
    }

    public function groups()
    {
        return $this->belongsToMany(GroupDatabase::class, 'group_database_members', 'database_id', 'group_id')
            ->with(['team' => fn($q) => $q->select('id', 'name', 'description')]);
    }

    public function latestActivity()
    {
        return $this->hasOne(ActivityLog::class, 'database_id')->latestOfMany();
    }

    public static function mostUsedDatabases()
    {
        $teamId = session('team_databases.team_id') ?? null;

        $query = self::withCount('queryMetrics')
            ->select('user_databases.*')
            ->withSum('queryMetrics', 'query_count')
            ->when($teamId, function ($query) use ($teamId) {
                $query->whereHas('groups.team', function ($q) use ($teamId) {
                    $q->where('id', $teamId);
                });
            })
            ->limit(10);

        $mostUsedDatabases = $query->get();

        return $mostUsedDatabases->map(function ($db) {
            return [
                'query_metrics_id' => $db->queryMetrics->first()?->id,
                'database_id' => $db->id,
                'team_id' => $db->groups->first()?->team_id,
                'groups' => $db->groups->map(fn($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'team_id' => $group->team_id
                ]),
                'database_name' => $db->database_name,
                'is_schema' => $db->is_schema,
                'query_metrics_sum_query_count' => $db->query_metrics_sum_query_count,
                'query_metrics_count' => $db->query_count,
                'created_at' => $db->created_at ? $db->created_at->format('Y-m-d H:i:s') : '',
            ];
        })->toArray();
    }

    public static function mostAffectedQueries()
    {
        $combinedAnalysis = DB::table(function ($query) {
            $query->select('main_id', 'query', 'rows_read', 'rows_written')
                ->from('top_queries')
                ->unionAll(
                    DB::table('slowest_queries')
                        ->select('main_id', 'query', 'rows_read', 'rows_written')
                );
        }, 'q')
            ->join('slowest_queries as sq', 'q.main_id', '=', 'sq.main_id')
            ->select('q.query')
            ->selectRaw('COUNT(*) as execution_count,
                AVG(sq.elapsed_ms) as avg_duration,
                SUM(q.rows_read) as total_rows_read,
                SUM(q.rows_written) as total_rows_written')
            ->groupBy('q.query')
            ->orderByDesc('execution_count')
            ->orderByDesc('avg_duration')
            ->limit(10)
            ->get();

        $mostAffectedQueries = [];
        foreach ($combinedAnalysis as $result) {
            array_push($mostAffectedQueries, [
                'query' => $result->query,
                'execution_count' => $result->execution_count,
                'avg_duration' => $result->avg_duration,
            ]);
        }
        return $mostAffectedQueries;
    }
}
