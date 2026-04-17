<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NutrientTag>
 */
class NutrientTagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name'        => ucwords($name),
            'slug'        => str_replace(' ', '-', strtolower($name)),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
