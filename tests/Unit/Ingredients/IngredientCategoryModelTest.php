<?php

namespace Tests\Unit\Ingredients;

use Tests\TestCase;
use App\Models\IngredientCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IngredientCategoryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_properties_are_correct(): void
    {
        $category = new IngredientCategory();

        $expectedFillable = ['name'];

        $this->assertEquals(
            $expectedFillable,
            $category->getFillable(),
            'IngredientCategory fillable attributes mismatch'
        );
    }

    public function test_can_create_category_with_mass_assignment(): void
    {
        $category = IngredientCategory::create([
            'name' => 'Fruits',
        ]);

        $this->assertDatabaseHas('ingredient_categories', [
            'id' => $category->id,
            'name' => 'Fruits',
        ]);
    }
}
