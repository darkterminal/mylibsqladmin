<?php

namespace App\Console\Commands;

use App\Services\StatsFetcherService;
use Illuminate\Console\Command;

class StatsFetcher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:stats-fetcher';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch each database statistics';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        StatsFetcherService::run();
    }
}
