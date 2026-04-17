<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Source>
 */
class SourceFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name'        => $name,
            'slug'        => str($name)->slug()->value() . '-' . fake()->unique()->randomNumber(6, true),
            'url'         => fake()->optional()->url(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}