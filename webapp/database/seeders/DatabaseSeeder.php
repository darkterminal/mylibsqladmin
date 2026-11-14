<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Fresh production installs only get permission scaffolding + a single
        // super-admin via BootstrapSeeder (SUPER_ADMIN_PASSWORD or random).
        // Demo fixture users are intentionally absent.
        $this->call([
            BootstrapSeeder::class,
        ]);

        if (filter_var(env('LIBSQL_SEED_DEMO_USERS', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->call([
                TeamSeeder::class,
            ]);
        }
    }
}
