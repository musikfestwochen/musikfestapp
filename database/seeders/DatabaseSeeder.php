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
        User::factory()->create([
            'name' => 'Simon',
            'email' => 'simon@musikfestapp.ch',
        ]);

        User::factory()->create([
            'name' => 'Pirmin',
            'email' => 'pirmin@musikfestapp.ch',
        ]);

        User::factory(20)->randomVerified()->create();
    }
}
