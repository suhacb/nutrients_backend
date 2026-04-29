<?php

namespace Database\Factories;

use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IngredientNutritionFact>
 */
class IngredientNutritionFactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ingredient_id'  => Ingredient::factory(),
            'category'       => fake()->word(),
            'name'           => fake()->word(),
            'amount'         => fake()->randomFloat(2, 0, 100),
            'amount_unit_id' => Unit::factory(),
        ];
    }
}
