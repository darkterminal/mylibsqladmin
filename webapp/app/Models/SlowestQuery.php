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

}
