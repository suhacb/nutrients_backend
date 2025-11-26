<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class IngredientNutrientPivot extends Pivot
{
    protected $table = 'ingredient_nutrient';

    protected $fillable = [
        'ingredient_id',
        'nutrient_id',
        'amount',
        'amount_unit_id',
        'portion_amount',
        'portion_amount_unit_id',
    ];
}
