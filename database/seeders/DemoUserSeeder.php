<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    /** Local demo accounts only (password: "password"). One per role. */
    public function run(): void
    {
        // Written by hand rather than User::factory() so this seeder also runs in
        // the production image, where dev dependencies like Faker are absent.
        User::updateOrCreate(
            ['email' => 'editor@example.com'],
            ['name' => 'Demo Editor', 'role' => 'editor', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );

        User::updateOrCreate(
            ['email' => 'viewer@example.com'],
            ['name' => 'Demo Viewer', 'role' => 'viewer', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );
    }
}
