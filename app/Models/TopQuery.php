<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TopQuery extends Model
{
    protected $table = 'top_queries';

    protected $fillable = [
        'main_id',
        'rows_written',
        'rows_read',
        'query',
    ];

    public function queryMetric()
    {
        return $this->belongsTo(QueryMetric::class, 'main_id');
    }

    public static function mostUsedQueries()
    {
        $mostUsedQueries = TopQuery::select('query')
            ->unionAll(
                DB::table('slowest_queries')->select('query')
            )
            ->groupBy('query')
            ->selectRaw('query, COUNT(*) as usage_count, 
        SUM(rows_read) as total_rows_read,
        SUM(rows_written) as total_rows_written')
            ->orderByDesc('usage_count')
            ->limit(10)
            ->get();

        $queries = [];
        foreach ($mostUsedQueries as $query) {
            array_push($queries, [
                'query' => $query->query,
                'usage_count' => $query->usage_count,
                'total_rows_read' => $query->total_rows_read
            ]);
        }

        return $queries;
    }
}
