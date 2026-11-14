<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

/**
 * Production bootstrap for a brand-new installation.
 *
 * Usage: php artisan db:seed --class=BootstrapSeeder
 *
 * - Applies RoleUserSeeder for the permission skeleton and fixtures.
 * - Re-stamps the super-admin account with the password from the
 *   SUPER_ADMIN_PASSWORD environment variable; if unset, generates a
 *   random one and writes it to storage/app/bootstrap-credentials.txt
 *   (mode 0600) — read it once, then delete it.
 * - Does NOT delete user data; safe to re-run (overwrites the file).
 */
class BootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $username = env('SUPER_ADMIN_USERNAME', 'superadmin');
        $email    = env('SUPER_ADMIN_EMAIL', 'superadmin@localhost');

        $password = env('SUPER_ADMIN_PASSWORD');
        $generated = !is_string($password) || $password === '';

        if ($generated) {
            $password = Str::random(16);
        }

        // Create the permission skeleton and fixture users (super-admin etc. —
        // source of truth for the seeded permission matrix).
        $this->call(RoleUserSeeder::class);

        // Re-stamp so the documented demo credentials never exist un-hashed
        // on a production instance.
        User::where('username', 'superadmin')->update([
            'username' => $username,
            'email'    => $email,
            'password' => Hash::make($password),
        ]);

        if ($generated) {
            $path = storage_path('app/bootstrap-credentials.txt');
            File::put($path, "username={$username}\npassword={$password}\n");
            @chmod($path, 0600);
        }
    }
}
