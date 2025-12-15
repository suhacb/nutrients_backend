<?php

namespace Tests\Unit\Ingredients;

use Tests\TestCase;
use App\Models\Unit;
use App\Models\IngredientNutrientPivot;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\MakesUnit;

class IngredientNutrientPivotTest extends TestCase
{
    use RefreshDatabase, MakesUnit;
    
    public function test_it_extends_pivot_class(): void
    {
        $pivot = new IngredientNutrientPivot();

        $this->assertInstanceOf(
            Pivot::class,
            $pivot,
            'IngredientNutrientPivot should extend Illuminate\Database\Eloquent\Relations\Pivot'
        );
    }

    public function test_it_has_correct_table_and_fillable_fields(): void
    {
        $pivot = new IngredientNutrientPivot();

        $this->assertEquals('ingredient_nutrient', $pivot->getTable());
        $this->assertEquals([
            'ingredient_id',
            'nutrient_id',
            'amount',
            'amount_unit_id',
            'portion_amount',
            'portion_amount_unit_id',
        ], $pivot->getFillable());
    }

    public function test_it_has_correct_casts(): void
    {
        $pivot = new IngredientNutrientPivot();

        $this->assertEquals([
            'amount' => 'float',
            'portion_amount' => 'float'
        ], $pivot->getCasts());
    }

    public function test_it_belongs_to_amount_unit(): void
    {
        $pivot = new IngredientNutrientPivot();
        $relation = $pivot->amount_unit();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals('amount_unit_id', $relation->getForeignKeyName());
    }

    public function test_it_belongs_to_portion_amount_unit(): void
    {
        $pivot = new IngredientNutrientPivot();
        $relation = $pivot->portion_amount_unit();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals('portion_amount_unit_id', $relation->getForeignKeyName());
    }

    public function test_pivot_can_access_units()
    {
        $amount_unit = $this->makeUnit();
        $portion_unit = $this->makeUnit();

        $pivot = IngredientNutrientPivot::factory()->create([
            'amount_unit_id' => $amount_unit->id,
            'portion_amount_unit_id' => $portion_unit->id,
        ]);

        $this->assertEquals($amount_unit->name, $pivot->amount_unit->name);
        $this->assertEquals($portion_unit->name, $pivot->portion_amount_unit->name);
    }
}
