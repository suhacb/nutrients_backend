<?php

namespace App\Models;

use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class IngredientIngredientCategory extends Pivot
{
    public $timestamps = false;
    protected $fillable = ['ingredient_id', 'ingredient_category_id'];

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'ingredient_ingredient_category')->using(IngredientIngredientCategory::class);
    }
}
