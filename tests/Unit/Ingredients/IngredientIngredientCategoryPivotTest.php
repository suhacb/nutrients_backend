<?php

namespace Tests\Unit\Ingredients;

use Tests\TestCase;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use Illuminate\Database\QueryException;
use App\Models\IngredientIngredientCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IngredientIngredientCategoryPivotTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_properties_are_correct(): void
    {
        $pivot = new IngredientIngredientCategory();

        $expectedFillable = ['ingredient_id', 'ingredient_category_id'];

        $this->assertEquals(
            $expectedFillable,
            $pivot->getFillable(),
            'Pivot fillable attributes mismatch'
        );
    }

    public function test_can_attach_ingredient_to_category(): void
    {
        $ingredient = Ingredient::factory()->create(['name' => 'Tomato']);
        $category = IngredientCategory::create(['name' => 'Vegetables']);

        // Attach via pivot
        $ingredient->categories()->attach($category->id);

        $this->assertDatabaseHas('ingredient_ingredient_category', [
            'ingredient_id' => $ingredient->id,
            'ingredient_category_id' => $category->id,
        ]);
    }

    public function test_prevents_duplicate_entries_due_to_primary_key(): void
    {
        $ingredient = Ingredient::factory()->create(['name' => 'Tomato 2']);
        $category = IngredientCategory::create(['name' => 'Vegetables 2']);

        $ingredient->categories()->attach($category->id);

        // Duplicate attach should throw a QueryException
        $this->expectException(QueryException::class);

        $ingredient->categories()->attach($category->id);
    }
}
