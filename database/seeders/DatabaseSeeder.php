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
        User::factory()->admin()->create([
            'name' => 'Ahmad Firdaus',
            'email' => 'admin@example.com',
        ]);

        User::factory()->staff()->create([
            'name' => 'Nurul Huda',
            'email' => 'staff@example.com',
        ]);

        $this->call(StorefrontSeeder::class);
    }
}
