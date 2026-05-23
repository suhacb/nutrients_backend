<?php

namespace Database\Factories;

use App\Models\Nutrient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Nutrient>
 */
class NutrientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => fake()->name(),
            'description' => fake()->paragraph(),
        ];
    }
}
