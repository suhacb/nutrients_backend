<?php

namespace Database\Factories;

use App\Models\Unit;
use App\Models\Nutrient;
use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IngredientNutrientPivot>
 */
class IngredientNutrientPivotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unit = Unit::factory()->create();
        $nutrient = Nutrient::factory()->create();
        $ingredient = Ingredient::factory()->create();
        return [
            'ingredient_id' => $ingredient->id,
            'nutrient_id' => $nutrient->id,
            'amount' => 100,
            'amount_unit_id' => $unit->id,
            'portion_amount' => 100,
            'portion_amount_unit_id' => $unit->id,
        ];
    }
}
