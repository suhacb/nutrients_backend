<?php

namespace Tests\Unit\Ingredients;

use Tests\TestCase;
use App\Models\IngredientNutrientPivot;
use Illuminate\Database\Eloquent\Relations\Pivot;

class IngredientNutrientPivotTest extends TestCase
{
    public function test_it_extends_pivot_class()
    {
        $pivot = new IngredientNutrientPivot();

        $this->assertInstanceOf(
            Pivot::class,
            $pivot,
            'IngredientNutrientPivot should extend Illuminate\Database\Eloquent\Relations\Pivot'
        );
    }

    public function test_it_has_correct_table_and_fillable_fields()
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
}
