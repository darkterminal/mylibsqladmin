<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

}
