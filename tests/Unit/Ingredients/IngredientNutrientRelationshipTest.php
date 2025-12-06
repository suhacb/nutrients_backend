<?php

namespace Tests\Unit\Ingredients;

use Tests\TestCase;
use App\Models\Unit;
use App\Models\Nutrient;
use App\Models\Ingredient;
use Illuminate\Support\Facades\Queue;
use Database\Seeders\UnitsTableSeeder;
use App\Models\IngredientNutrientPivot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\MakesUnit;

class IngredientNutrientRelationshipTest extends TestCase
{
    use RefreshDatabase, MakesUnit;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // prevent search sync jobs from dispatching
    }

    public function test_ingredient_can_have_many_nutrients_via_pivot_with_units(): void
    {
        // Create some units
        [$mg, $g] = $this->makeUnit(2);
        
        // Create an ingredient and nutrients
        $ingredient = Ingredient::factory()->create([
            'default_amount' => 100,
            'default_amount_unit_id' => $g->id,
        ]);

        $nutrientA = Nutrient::factory()->create(['name' => 'Magnesium']);
        $nutrientB = Nutrient::factory()->create(['name' => 'Phosphorus']);

        // Attach nutrients to ingredient
        $ingredient->nutrients()->attach($nutrientA->id, [
            'amount' => 12.5,
            'amount_unit_id' => $mg->id,
        ]);

        $ingredient->nutrients()->attach($nutrientB->id, [
            'amount' => 189.0,
            'amount_unit_id' => $mg->id,
        ]);

        $ingredient->refresh();

        $this->assertCount(2, $ingredient->nutrients);
        $this->assertEquals(12.5, $ingredient->nutrients[0]->pivot->amount);
        $this->assertEquals($mg->id, $ingredient->nutrients[0]->pivot->amount_unit_id);

        // Check default unit relation
        $this->assertTrue($ingredient->default_amount_unit->is($g));

        // Check pivot resolves amount_unit relation
        $pivot = IngredientNutrientPivot::first();
        $this->assertTrue($pivot->amount_unit->is($mg));
    }

    public function test_nutrient_can_have_many_ingredients_via_pivot(): void
    {
        $unit = $this->makeUnit();
        $nutrient = Nutrient::factory()->create();
        $ingredientA = Ingredient::factory()->create();
        $ingredientB = Ingredient::factory()->create();

        // Attach via pivot
        $nutrient->ingredients()->attach($ingredientA->id, [
            'amount' => 1.2,
            'amount_unit_id' => $unit->id,
        ]);
        $nutrient->ingredients()->attach($ingredientB->id, [
            'amount' => 2.4,
            'amount_unit_id' => $unit->id,
        ]);

        $this->assertCount(2, $nutrient->ingredients);
        $this->assertEquals(1.2, $nutrient->ingredients->first()->pivot->amount);
    }

    public function test_soft_deleting_ingredient_preserves_pivot_records(): void
    {
        $unit = $this->makeUnit();
        $ingredient = Ingredient::factory()->create();
        $nutrient = Nutrient::factory()->create();

        $ingredient->nutrients()->attach($nutrient->id, [
            'amount' => 5,
            'amount_unit_id' => $unit->id,
        ]);

        $this->assertDatabaseCount('ingredient_nutrient', 1);

        // Soft delete
        $ingredient->delete();

        // Pivot should still exist
        $this->assertDatabaseCount('ingredient_nutrient', 1);

        // Optional: restore ingredient and check relation still intact
        $ingredient->restore();
        $this->assertTrue($ingredient->nutrients()->where('nutrient_id', $nutrient->id)->exists());
    }

    public function test_force_deleting_ingredient_removes_pivot_records(): void
    {
        $unit = $this->makeUnit();
        $ingredient = Ingredient::factory()->create();
        $nutrient = Nutrient::factory()->create();

        $ingredient->nutrients()->attach($nutrient->id, [
            'amount' => 5,
            'amount_unit_id' => $unit->id,
        ]);

        $this->assertDatabaseCount('ingredient_nutrient', 1);

        // Force delete
        $ingredient->forceDelete();

        // Pivot should now be removed
        $this->assertDatabaseCount('ingredient_nutrient', 0);
    }
}
