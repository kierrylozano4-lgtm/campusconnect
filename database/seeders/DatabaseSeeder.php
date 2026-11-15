<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            User::factory()->raw([
                'full_name' => 'Test User',
                'email' => 'test@example.com',
            ])
        );

        $this->call(MarketplaceDemoSeeder::class);
    }
}
