<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Recipe>
 */
class RecipeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'         => ucwords(fake()->words(3, true)),
            'description'  => fake()->optional()->paragraph(),
            'instructions' => fake()->optional()->paragraphs(3, true),
            'portions'     => fake()->numberBetween(1, 8),
            'source_url'   => fake()->optional()->url(),
            'sync_status'  => 'pending',
        ];
    }
}
