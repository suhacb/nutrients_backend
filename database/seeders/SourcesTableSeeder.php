<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SourcesTableSeeder extends Seeder
{
    public function run(): void
    {
        Source::firstOrCreate(
            ['slug' => 'system'],
            [
                'name'        => 'System',
                'description' => 'Canonical nutrient taxonomy curated by the system.',
            ]
        );

        Source::firstOrCreate(
            ['slug' => 'usda-food-data-central'],
            [
                'name'        => 'USDA FoodData Central',
                'url'         => 'https://fdc.nal.usda.gov/',
                'description' => 'USDA FoodData Central — a comprehensive food and nutrient database maintained by the U.S. Department of Agriculture.',
            ]
        );
    }
}
