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
}
