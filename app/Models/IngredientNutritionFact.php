<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientNutritionFact extends Model
{
    use HasFactory;

    protected $fillable = [
        'ingredient_id',
        'category',
        'name',
        'amount',
        'amount_unit_id',
    ];

    protected $casts = [
        'amount' => 'float'
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'amount_unit_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }
}
