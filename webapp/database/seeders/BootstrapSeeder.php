<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Production bootstrap for a brand-new installation.
 *
 * Usage: php artisan db:seed --class=BootstrapSeeder
 *
 * - Applies the idempotent permission/role skeleton (RoleUserSeeder).
 *   It never deletes users, roles or permissions.
 * - Creates the single super-admin if it does not exist (username/email
 *   default to SUPER_ADMIN_USERNAME / SUPER_ADMIN_EMAIL or
 *   superadmin / superadmin@localhost).
 * - (Re)stamps the super-admin password from SUPER_ADMIN_PASSWORD; if
 *   unset, generates a random one and writes it to
 *   storage/app/bootstrap-credentials.txt (mode 0600).
 *   Read the file once, then delete it.
 * - Safe to re-run: it will rotate the password, never wipe data, and
 *   never leave an instance without a super-admin.
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

        // 1) Idempotent permission skeleton (no destructive actions).
        $this->call(RoleUserSeeder::class);

        // 2) Create or re-stamp the single super-admin account.
        $user = User::withTrashed()->where('username', $username)->first();

        // Adopt a legacy fixture account if present (its password is
        // re-stamped below, so public demo credentials never survive).
        if (!$user && $username !== 'superadmin') {
            $user = User::withTrashed()->where('username', 'superadmin')->first();
        }

        if ($user) {
            if (method_exists($user, 'restore') && $user->trashed()) {
                $user->restore();
            }

            $user->forceFill([
                'name'              => 'Super Admin',
                'username'          => $username,
                'email'             => $email,
                'password'          => Hash::make($password),
                'is_active'         => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        } else {
            $user = User::create([
                'name'              => 'Super Admin',
                'username'          => $username,
                'email'             => $email,
                'password'          => Hash::make($password),
                'is_active'         => true,
                'email_verified_at' => now(),
            ]);
        }

        // 3) Attach the Super Admin role (idempotent).
        $role = Role::where('name', 'Super Admin')->firstOrFail();
        $user->roles()->syncWithoutDetaching([$role->id]);

        // 4) Write one-time credentials for generated passwords only.
        if ($generated) {
            $path = storage_path('app/bootstrap-credentials.txt');
            File::put($path, "username={$username}\npassword={$password}\n");
            @chmod($path, 0600);

            $this->command?->warn('Super-admin credentials written to storage/app/bootstrap-credentials.txt — read once, then delete it.');
        } else {
            $this->command?->info('Super-admin password set from SUPER_ADMIN_PASSWORD environment variable.');
        }
    }
}
