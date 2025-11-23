<?php

namespace Database\Factories;

use App\Models\Nutrient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Nutrient>
 */
class NutrientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $source = 'USDA FoodData Central';
        return [
            'source' => $source,
            'external_id' => function() use ($source) {
                do {
                    $number = fake()->numberBetween(1, 999999); // generate random number
                    $exists = Nutrient::where([
                        'external_id' => $number,
                        'source' => $source
                    ])->exists();
                } while ($exists);
                return strval($number);
            },
            'name' => fake()->name(),
            'description' => fake()->paragraph(),
            'derivation_code' => fake()->randomElement(range('A', 'Z')),
            'derivation_description' => fake()->text(255),
        ];
    }
}
