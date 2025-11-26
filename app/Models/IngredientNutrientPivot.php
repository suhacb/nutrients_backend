<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    protected $casts = [
        'amount' => 'float',
        'portion_amount' => 'float'
    ];

    public function amount_unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'amount_unit_id');
    }

    public function portion_amount_unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'portion_amount_unit_id');
    }
}
