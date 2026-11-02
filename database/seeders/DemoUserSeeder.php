<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    /** Local demo accounts only (factory password: "password"). One per role. */
    public function run(): void
    {
        User::factory()->editor()->create([
            'name' => 'Demo Editor',
            'email' => 'editor@example.com',
        ]);

        User::factory()->create([
            'name' => 'Demo Viewer',
            'email' => 'viewer@example.com',
        ]);
    }
}
