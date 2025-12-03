<?php

namespace Tests\Unit\Ingredients;

use Tests\TestCase;
use App\Models\Unit;
use App\Models\Ingredient;
use App\Models\IngredientNutritionFact;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IngredientNutritionFactModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes(): void
    {
        $fillable = (new IngredientNutritionFact())->getFillable();

        $expectedFillable = [
            'ingredient_id',
            'category',
            'name',
            'amount',
            'amount_unit_id',
        ];

        $this->assertEqualsCanonicalizing($expectedFillable, $fillable, 'Fillable attributes do not match expected');
    }

    public function test_model_can_be_created_with_fillable_attributes(): void
    {
        $ingredient = Ingredient::factory()->create();
        $unit = Unit::factory()->create();

        $nutrient = IngredientNutritionFact::create([
            'ingredient_id' => $ingredient->id,
            'category' => 'macro',
            'name' => 'Protein',
            'amount' => 10.0,
            'amount_unit_id' => $unit->id,
        ]);

        $this->assertDatabaseHas('ingredient_nutrition_facts', [
            'id' => $nutrient->id,
            'name' => 'Protein',
            'amount' => 10.0,
        ]);
    }

    public function test_amount_is_cast_to_double(): void
    {
        $ingredient = Ingredient::factory()->create();
        $unit = Unit::factory()->create();

        $nutrient = IngredientNutritionFact::create([
            'ingredient_id' => $ingredient->id,
            'category' => 'macro',
            'name' => 'Fat',
            'amount' => 5,
            'amount_unit_id' => $unit->id,
        ]);

        $this->assertIsFloat($nutrient->amount);
        $this->assertEquals(5.0, $nutrient->amount);
    }

    public function test_relationships_to_unit_and_ingredient(): void
    {
        $ingredient = Ingredient::factory()->create();
        $unit = Unit::factory()->create();

        $nutrition_fact = IngredientNutritionFact::create([
            'ingredient_id' => $ingredient->id,
            'category' => 'micro',
            'name' => 'Vitamin A',
            'amount' => 0.2,
            'amount_unit_id' => $unit->id,
        ]);

        $this->assertInstanceOf(Ingredient::class, $nutrition_fact->ingredient);
        $this->assertInstanceOf(Unit::class, $nutrition_fact->unit);
    }
}
