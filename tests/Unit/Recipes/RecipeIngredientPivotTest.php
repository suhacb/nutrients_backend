<?php

namespace Tests\Unit\Recipes;

use App\Models\RecipeIngredient;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\MakesUnit;
use Tests\TestCase;

class RecipeIngredientPivotTest extends TestCase
{
    use RefreshDatabase, MakesUnit;

    public function test_it_extends_pivot_class(): void
    {
        $this->assertInstanceOf(Pivot::class, new RecipeIngredient());
    }

    public function test_it_has_correct_table_and_fillable_fields(): void
    {
        $pivot = new RecipeIngredient();

        $this->assertEquals('recipe_ingredient', $pivot->getTable());
        $this->assertEquals(
            ['recipe_id', 'ingredient_id', 'amount', 'unit_id'],
            $pivot->getFillable()
        );
    }

    public function test_it_has_correct_casts(): void
    {
        $this->assertEquals(['amount' => 'float'], (new RecipeIngredient())->getCasts());
    }

    public function test_unit_relationship_is_belongs_to(): void
    {
        $relation = (new RecipeIngredient())->unit();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals('unit_id', $relation->getForeignKeyName());
    }

    public function test_pivot_can_access_unit(): void
    {
        Queue::fake();

        $unit       = $this->makeUnit();
        $ingredient = \App\Models\Ingredient::factory()->create(['default_amount_unit_id' => $unit->id]);
        $recipe     = \App\Models\Recipe::factory()->create();

        $recipe->ingredients()->attach($ingredient->id, [
            'amount'  => 150.0,
            'unit_id' => $unit->id,
        ]);

        $pivot = $recipe->ingredients()->first()->pivot;
        $pivot->load('unit');

        $this->assertInstanceOf(Unit::class, $pivot->unit);
        $this->assertEquals($unit->id, $pivot->unit->id);
    }
}
