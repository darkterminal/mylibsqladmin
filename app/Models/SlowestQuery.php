<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlowestQuery extends Model
{
    protected $table = 'slowest_queries';

    protected $fillable = [
        'main_id',
        'query',
        'rows_read',
        'rows_written',
        'elapsed_ms',
    ];

    public function queryMetric()
    {
        return $this->belongsTo(QueryMetric::class, 'main_id');
    }

    public static function slowestQueries()
    {
        $slowestQueries = self::select('query')
            ->selectRaw('AVG(elapsed_ms) as avg_duration, COUNT(*) as occurrences')
            ->groupBy('query')
            ->orderByDesc('avg_duration')
            ->limit(10)
            ->get();

        $queries = [];
        foreach ($slowestQueries as $query) {
            array_push($queries, [
                'query' => $query->query,
                'avg_duration' => $query->avg_duration,
                'occurrences' => $query->occurrences
            ]);
        }

        return $queries;
    }
}
