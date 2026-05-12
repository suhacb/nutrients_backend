<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class RecipeIngredient extends Pivot
{
    use HasFactory;

    protected $table = 'recipe_ingredient';

    protected $fillable = ['recipe_id', 'ingredient_id', 'amount', 'unit_id'];

    protected $casts = ['amount' => 'float'];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
