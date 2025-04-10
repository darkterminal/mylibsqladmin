<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class QueryMetric extends Model
{
    protected $table = 'query_metrics';
    protected $fillable = [
        'database_id',
        'rows_read_count',
        'rows_written_count',
        'storage_bytes_used',
        'write_requests_delegated',
        'replication_index',
        'embedded_replica_frames_replicated',
        'query_count',
        'elapsed_ms',
        'queries',
    ];

    public function database()
    {
        return $this->belongsTo(UserDatabase::class);
    }

    public function topQueries()
    {
        return $this->hasMany(TopQuery::class, 'main_id');
    }

    public function slowestQueries()
    {
        return $this->hasMany(SlowestQuery::class, 'main_id');
    }

    // get query metrics for last 24 hours
    public static function scopeLast24Hours()
    {
        return self::with(['topQueries', 'slowestQueries'])
            ->where('created_at', '>', now()->subDays(1))
            ->orderBy('id', 'desc')
            ->get();
    }

    public static function chartData()
    {
        return self::with(['topQueries', 'slowestQueries'])
            ->orderBy('id', 'desc')
            ->get();
    }

    public static function summarized()
    {
        return self::scopeLast24Hours()
            ->map(function ($metric) {
                $database = UserDatabase::find($metric->database_id);

                if (!$database) {
                    return null;
                }

                return [
                    'id' => $metric->id,
                    'name' => $database->database_name,
                    'rows_read_count' => $metric->rows_read_count ?? 0,
                    'rows_written_count' => $metric->rows_written_count ?? 0,
                    'storage_bytes_used' => $metric->storage_bytes_used ?? 0,
                    'query_count' => $metric->query_count ?? 0,
                    'elapsed_ms' => $metric->elapsed_ms ?? 0,
                    'write_requests_delegated' => $metric->write_requests_delegated ?? 0,
                    'replication_index' => $metric->replication_index ?? 0,
                    'embedded_replica_frames_replicated' => $metric->embedded_replica_frames_replicated ?? 0,
                    'queries' => empty($metric->queries) ? [] : json_decode($metric->queries, true),
                    'top_queries' => $metric->topQueries()->orderBy('rows_read', 'desc')->get()->map(fn($query) => [
                        'rows_written' => $query->rows_written ?? 0,
                        'rows_read' => $query->rows_read ?? 0,
                        'query' => $query->query ?? ''
                    ]),
                    'slowest_queries' => $metric->slowestQueries()->orderBy('elapsed_ms', 'desc')->get()->map(fn($query) => [
                        'rows_written' => $query->rows_written ?? 0,
                        'rows_read' => $query->rows_read ?? 0,
                        'query' => $query->query ?? '',
                        'elapsed_ms' => $query->elapsed_ms ?? 0
                    ]),
                    'created_at' => Carbon::parse($metric->created_at)
                        ->setTimezone(env('APP_TIMEZONE', 'UTC'))
                        ->format('H:i:s')
                ];
            })
            ->filter() // Remove null entries
            ->sortByDesc('created_at')
            ->unique(fn($item) => implode('|', [
                $item['rows_read_count'],
                $item['rows_written_count'],
                $item['query_count'],
                $item['storage_bytes_used']
            ]))
            ->values()
            ->toArray();
    }

    public function scopeMinimalSummarized($query)
    {
        return $query->selectRaw('
            SUM(rows_read) as rows_read_count,
            SUM(rows_written) as rows_written_count,
            COUNT(*) as query_count,
            SUM(storage_bytes_used) as storage_bytes_used
        ');
    }
}
