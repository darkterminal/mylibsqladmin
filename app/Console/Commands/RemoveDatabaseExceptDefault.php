<?php

namespace App\Console\Commands;

use App\Services\SqldService;
use Illuminate\Console\Command;

class RemoveDatabaseExceptDefault extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sqld:remove-database-except-default';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove database except default';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Removing database except default');
        SqldService::deleteDatabaseExcept('default');
    }
}
