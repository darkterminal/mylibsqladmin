<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    public static function scopeLast24Hours(int $userDatabaseId)
    {
        return self::where('database_id', $userDatabaseId)
            ->where('created_at', '>', now()
                ->subDays(1))
            ->get();
    }

    public static function chartData()
    {
        return self::with(['topQueries', 'slowestQueries'])->orderBy('id', 'desc')->get();
    }

    // Get metrics for a specific database with time-based aggregation
    public static function getDatabaseChartData($databaseId)
    {
        $now = now();
        $timeFormat = 'Y-m-d H:00'; // Hourly grouping

        // Get metrics for last 5 periods (adjust timeframe as needed)
        $metrics = self::where('database_id', $databaseId)
            ->where('created_at', '>=', $now->copy()->subHours(4))
            ->selectRaw("
            strftime('%Y-%m-%d %H:00', created_at) as time_interval,
            SUM(rows_read_count) as rows_read,
            SUM(rows_written_count) as rows_written,
            SUM(query_count) as queries,
            AVG(storage_bytes_used) as storage,
            database_id,
            id as metric_id
        ")
            ->groupBy('time_interval')
            ->orderBy('time_interval')
            ->get()
            ->keyBy('time_interval');

        // Generate time points for last 5 hours
        $chartData = [];
        for ($i = 4; $i >= 0; $i--) {
            $time = $now->copy()->subHours($i)->format($timeFormat);
            $data = $metrics->get($time, [
                'rows_read' => 0,
                'rows_written' => 0,
                'queries' => 0,
                'storage' => 0,
                'database_id' => $databaseId,
                'metric_id' => 0
            ]);

            $chartData[] = [
                'name' => $i === 0 ? 'Current' : 'T-' . (4 - $i + 1),
                'rows_read' => (int) $data['rows_read'],
                'rows_written' => (int) $data['rows_written'],
                'queries' => (int) $data['queries'],
                'storage' => (int) $data['storage'],
                'database_id' => $data['database_id'],
                'metric_id' => $data['metric_id'],
            ];
        }

        return $chartData;
    }
}
