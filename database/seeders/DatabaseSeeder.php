<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\NutrientsTableSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UnitsTableSeeder::class,
            SourcesTableSeeder::class,
            NutrientsTableSeeder::class,
        ]);
    }
}
