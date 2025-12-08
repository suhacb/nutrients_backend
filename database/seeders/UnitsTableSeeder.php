<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UnitsTableSeeder extends Seeder
{
    protected $units = [
        // Mass units
        ['name' => 'kilogram', 'abbreviation' => 'kg', 'type' => 'mass'],
        ['name' => 'gram', 'abbreviation' => 'g', 'type' => 'mass'],
        ['name' => 'decagram', 'abbreviation' => 'dag', 'type' => 'mass'],
        ['name' => 'milligram', 'abbreviation' => 'mg', 'type' => 'mass'],
        ['name' => 'microgram', 'abbreviation' => 'µg', 'type' => 'mass'],
        ['name' => 'pound', 'abbreviation' => 'lb', 'type' => 'mass'],
        ['name' => 'ounce', 'abbreviation' => 'oz', 'type' => 'mass'],
        ['name' => 'stone', 'abbreviation' => 'st', 'type' => 'mass'],
        ['name' => 'milligram Activity Equivalent', 'abbreviation' => 'mg_ATE', 'type' => 'mass'],

        // Volume units
        ['name' => 'liter', 'abbreviation' => 'L', 'type' => 'volume'],
        ['name' => 'deciliter', 'abbreviation' => 'dL', 'type' => 'volume'],
        ['name' => 'milliliter', 'abbreviation' => 'mL', 'type' => 'volume'],
        ['name' => 'teaspoon', 'abbreviation' => 'tsp', 'type' => 'volume'],
        ['name' => 'tablespoon', 'abbreviation' => 'tbsp', 'type' => 'volume'],
        ['name' => 'fluid ounce', 'abbreviation' => 'fl oz', 'type' => 'volume'],
        ['name' => 'cup', 'abbreviation' => 'cup', 'type' => 'volume'],
        ['name' => 'pint', 'abbreviation' => 'pt', 'type' => 'volume'],
        ['name' => 'quart', 'abbreviation' => 'qt', 'type' => 'volume'],
        ['name' => 'gallon', 'abbreviation' => 'gal', 'type' => 'volume'],

        // Energy units
        ['name' => 'joule', 'abbreviation' => 'J', 'type' => 'energy'],
        ['name' => 'kilojoule', 'abbreviation' => 'kJ', 'type' => 'energy'],
        ['name' => 'kilocalorie', 'abbreviation' => 'kcal', 'type' => 'energy'],

        // Other
        ['name' => 'international unit', 'abbreviation' => 'IU', 'type' => 'other'],
        ['name' => 'specific gravity', 'abbreviation' => 'sp gr', 'type' => 'other']
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect($this->units)->each(function($unit) {
            Unit::firstOrCreate($unit, $unit);
        });
    }
}
