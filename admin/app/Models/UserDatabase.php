<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UserDatabase extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'database_name',
        'is_schema',
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
        return $this->belongsToMany(GroupDatabase::class, 'group_database_members', 'database_id', 'group_id');
    }

    public static function mostUsedDatabases()
    {
        $mostUsedDatabases = self::withCount('queryMetrics')
            ->select('database_name', 'id', 'is_schema', 'created_at')
            ->withSum('queryMetrics', 'query_count')
            ->limit(10)
            ->get();

        $databases = [];

        foreach ($mostUsedDatabases as $db) {
            array_push($databases, [
                'query_metrics_id' => $db->queryMetrics()->first()?->id,
                'database_id' => $db->id,
                'database_name' => $db->database_name,
                'is_schema' => $db->is_schema,
                'query_metrics_sum_query_count' => $db->query_metrics_sum_query_count,
                'query_metrics_count' => $db->query_count,
                'created_at' => Carbon::parse($db->created_at)->format('Y-m-d H:i:S')
            ]);
        }
        return $databases;
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
