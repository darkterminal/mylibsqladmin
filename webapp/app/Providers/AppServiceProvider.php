<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        date_default_timezone_set(config('app.timezone'));

        // The sqlite/libsql file database is shared between web requests and
        // the queue worker. With the default DELETE journal and no busy
        // timeout, any concurrent write makes the other side fail instantly
        // with `database is locked`. WAL allows concurrent readers and the
        // busy timeout makes writers wait instead of erroring. journal_mode
        // persists on the database file, so both statements are cheap; they
        // are applied lazily on the first query of each connection. Remote
        // libsql (Turso) connections are skipped — pragmas belong to the
        // local file only.
        $default = config('database.default');
        $config = config("database.connections.{$default}") ?? [];
        $isLocalSqliteFlavor = in_array($config['driver'] ?? null, ['sqlite', 'libsql'], true)
            && empty($config['url'] ?? null);

        if ($isLocalSqliteFlavor) {
            $connection = DB::connection();
            $connection->beforeExecuting(function () use ($connection): void {
                static $primed = false;

                if ($primed) {
                    return;
                }

                $primed = true;
                $connection->statement('PRAGMA busy_timeout = 5000');
                $connection->select('PRAGMA journal_mode = WAL');
            });
        }
    }
}
