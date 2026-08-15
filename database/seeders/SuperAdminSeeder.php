<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates five super-admin accounts.
 *
 * The `admins` table has no role column in this application — every admin has
 * full panel access — so these five are all super-admins by definition.
 *
 * Idempotent: existing accounts (matched on username) are left untouched, so
 * re-running never resets a password that someone has already changed.
 *
 * Run with:  php artisan db:seed --class=SuperAdminSeeder
 *
 * The default password can be overridden per environment:
 *   SUPERADMIN_PASSWORD=your-strong-password  (in .env)
 */
class SuperAdminSeeder extends Seeder {

    public function run(): void {
        // A single shared default password for the batch. Override via .env for
        // anything beyond local development, and change it after first login.
        $password = env('SUPERADMIN_PASSWORD', 'Admin@12345');

        $admins = [
            ['name' => 'Super Admin 1', 'username' => 'superadmin1', 'email' => 'superadmin1@quizmitra.com'],
            ['name' => 'Super Admin 2', 'username' => 'superadmin2', 'email' => 'superadmin2@quizmitra.com'],
            ['name' => 'Super Admin 3', 'username' => 'superadmin3', 'email' => 'superadmin3@quizmitra.com'],
            ['name' => 'Super Admin 4', 'username' => 'superadmin4', 'email' => 'superadmin4@quizmitra.com'],
            ['name' => 'Super Admin 5', 'username' => 'superadmin5', 'email' => 'superadmin5@quizmitra.com'],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($admins as $data) {
            // firstOrCreate keyed on username → never clobbers an existing admin.
            $admin = Admin::firstOrCreate(
                ['username' => $data['username']],
                [
                    'name'              => $data['name'],
                    'email'             => $data['email'],
                    'password'          => Hash::make($password),
                    'email_verified_at' => now(),
                ]
            );

            if ($admin->wasRecentlyCreated) {
                $created++;
                $this->command?->info("Created: {$data['username']}  ({$data['email']})");
            } else {
                $skipped++;
                $this->command?->warn("Exists, skipped: {$data['username']}");
            }
        }

        $this->command?->info("Super-admins — created: {$created}, skipped: {$skipped}.");
        if ($created > 0) {
            $this->command?->info("Login at /admin with username + password: {$password}");
            $this->command?->warn('Change these passwords after first login.');
        }
    }
}
