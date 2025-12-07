<?php

namespace App\Models;

use App\Models\IngredientCategory;
use App\Jobs\SyncIngredientToSearch;
use App\Models\IngredientNutrientPivot;
use App\Models\IngredientNutritionFact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ingredient extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'external_id',
        'source',
        'class',
        'name',
        'description',
        'default_amount',
        'default_amount_unit_id',
    ];

    protected static function booted()
    {
        static::created(function (Ingredient $ingredient) {
            $ingredient->loadForSearch();
            SyncIngredientToSearch::dispatch($ingredient, 'insert')->onQueue('ingredients');
        });

        static::updated(function (Ingredient $ingredient) {
            $ingredient->loadForSearch();
            SyncIngredientToSearch::dispatch($ingredient, 'update')->onQueue('ingredients');
        });

        static::deleting(function(Ingredient $ingredient) {
            if ($ingredient->isForceDeleting()) {
                $ingredient->nutrients()->detach();
            }
        });

        static::deleted(function (Ingredient $ingredient) {
            SyncIngredientToSearch::dispatch((object)['id' => $ingredient->id], 'delete')->onQueue('ingredients');
        });

        static::restored(function (Ingredient $ingredient) {
            $ingredient->loadForSearch();
            SyncIngredientToSearch::dispatch($ingredient, 'insert')->onQueue('ingredients');
        });
    }

    public function nutrients(): BelongsToMany
    {
        return $this->belongsToMany(Nutrient::class, 'ingredient_nutrient')
            ->using(IngredientNutrientPivot::class)
            ->withPivot(['amount', 'amount_unit_id', 'portion_amount', 'portion_amount_unit_id'])
            ->withTimestamps();
    }

    public function default_amount_unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'default_amount_unit_id');
    }

    public function nutrition_facts(): HasMany
    {
        return $this->hasMany(IngredientNutritionFact::class, 'ingredient_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(IngredientCategory::class, 'ingredient_ingredient_category')->using(IngredientIngredientCategory::class);
    }

    /**
     * Load relationships needed for ZincSearch payload.
     */
    public function loadForSearch(): self
    {
        // Preload default_amount_unit, nutrients and nutrition facts
        $this->load([
            'default_amount_unit',
            'nutrients',
            'nutrition_facts',
            'categories'
        ]);

        // Then explicitly eager load pivot relationships for nutrients
        $this->nutrients->each(function ($nutrient) {
            $nutrient->pivot->load(['amount_unit', 'portion_amount_unit']);
        });

        return $this;
    }

}
