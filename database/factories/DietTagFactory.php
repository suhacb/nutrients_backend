<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DietTag>
 */
class DietTagFactory extends Factory
{
    public function definition(): array
    {
        $name = ucwords(fake()->unique()->words(2, true));

        return [
            'name'        => $name,
            'slug'        => \Illuminate\Support\Str::slug($name),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
