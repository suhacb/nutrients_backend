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
        return [
            'ingredient_id' => Ingredient::factory(),
            'nutrient_id'   => Nutrient::factory(),
            'amount'        => 100,
            'amount_unit_id' => function () {
                $attrs = Unit::factory()->make()->toArray();
                return Unit::firstOrCreate(['abbreviation' => $attrs['abbreviation']], $attrs)->id;
            },
        ];
    }
}
