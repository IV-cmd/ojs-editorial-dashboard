<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Local demo accounts only (factory password: "password"). One per role.
        User::factory()->editor()->create([
            'name' => 'Demo Editor',
            'email' => 'editor@example.com',
        ]);

        User::factory()->create([
            'name' => 'Demo Viewer',
            'email' => 'viewer@example.com',
        ]);

        $this->call(OjsSampleSeeder::class);
    }
}
