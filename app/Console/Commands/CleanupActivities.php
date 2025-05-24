<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ActivityLog;
use Carbon\Carbon;

class CleanupActivities extends Command
{
    protected $signature = 'activities:cleanup';
    protected $description = 'Remove activities older than 7 days';

    public function handle()
    {
        ActivityLog::where('created_at', '<', Carbon::now()->subDays(7))
            ->delete();

        $this->info('Cleaned up old activities');
    }
}
