<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Unit>
 */
class UnitFactory extends Factory
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
        ['name' => 'international unit', 'abbreviation' => 'IU', 'type' => 'other']
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unit = Arr::random($this->units);

        return [
            'id' => fake()->numberBetween(1, 1000),
            'name' => $unit['name'],
            'abbreviation' => $unit['abbreviation'],
            'type' => $unit['type'],
            'created_at' => now(),
            'updated_at' => now()
        ];
    }
}
