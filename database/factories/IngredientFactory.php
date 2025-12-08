<?php

namespace Database\Factories;

use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ingredient>
 */
class IngredientFactory extends Factory
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
            'external_id' => function() use ($source) {
                do {
                    $number = fake()->numberBetween(1, 999999); // generate random number
                    $exists = Ingredient::where([
                        'external_id' => $number,
                        'source' => $source
                    ])->exists();
                } while ($exists);
                return strval($number);
            },
            'source' => $source,
            'class' => 'final',
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->paragraphs($this->faker->numberBetween(1, 4), true),
            'default_amount' => 100,
            'default_amount_unit_id' => $this->default_amount_unit_id ?? Unit::factory(),
        ];
    }
}
