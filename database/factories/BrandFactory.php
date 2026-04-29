<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Brand>
 */
class BrandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name'    => $name,
            'owner'   => fake()->company(),
            'slug'    => str($name)->slug()->value() . '-' . fake()->unique()->randomNumber(6, true),
            'country' => 'United States',
        ];
    }
}
