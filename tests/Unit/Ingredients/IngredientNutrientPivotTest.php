<?php

namespace Tests\Unit\Ingredients;

use Tests\TestCase;
use App\Models\IngredientNutrientPivot;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Unit;

class IngredientNutrientPivotTest extends TestCase
{
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
        $amount_unit = Unit::factory()->create(['name' => 'Gram', 'abbreviation' => 'g']);
        $portion_unit = Unit::factory()->create(['name' => 'Milligram', 'abbreviation' => 'mg']);

        $pivot = IngredientNutrientPivot::factory()->create([
            'amount_unit_id' => $amount_unit->id,
            'portion_amount_unit_id' => $portion_unit->id,
        ]);

        $this->assertEquals('Gram', $pivot->amountUnit->name);
        $this->assertEquals('Milligram', $pivot->portionAmountUnit->name);
    }
}
