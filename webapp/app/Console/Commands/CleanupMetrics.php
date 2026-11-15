<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CleanupMetrics extends Command
{
    protected $signature = 'metrics:cleanup';
    protected $description = 'Remove query metrics (and child rows) older than METRICS_RETENTION_DAYS (default 30 days)';

    public function handle(): void
    {
        $days = max(1, (int) env('METRICS_RETENTION_DAYS', 30));
        $cutoff = Carbon::now()->subDays($days);

        $deleted = 0;

        DB::table('query_metrics')
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$deleted) {
                $ids = collect($rows)->pluck('id');

                // Children are not guaranteed FK-enforcing on every driver;
                // delete them explicitly inside the chunk.
                DB::table('top_queries')->whereIn('main_id', $ids)->delete();
                DB::table('slowest_queries')->whereIn('main_id', $ids)->delete();
                DB::table('query_metrics')->whereIn('id', $ids)->delete();

                $deleted += $ids->count();
            });

        $this->info("Cleaned up {$deleted} metric rows older than {$days} days");
    }
}
