<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Creates the initial admin user from SEED_ADMIN_EMAIL/SEED_ADMIN_PASSWORD
     * env vars, if set and not already present — mirrors a bootstrap-admin
     * pattern (idempotent, safe to leave the env vars set across re-runs),
     * rather than hardcoded fake data.
     */
    public function run(): void
    {
        $email = env('SEED_ADMIN_EMAIL');
        $password = env('SEED_ADMIN_PASSWORD');

        if (blank($email)) {
            return;
        }

        if (User::where('email', $email)->exists()) {
            return;
        }

        User::create([
            'name' => 'Admin',
            'email' => $email,
            'password' => $password,
            'role' => User::ROLE_ADMIN,
        ]);
    }
}
