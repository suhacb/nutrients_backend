<?php

namespace App\Models;

use App\Enums\SyncStatus;
use App\Jobs\SyncIngredientToSearch;
use App\Models\IngredientCategory;
use App\Models\IngredientNutrientPivot;
use App\Models\IngredientNutritionFact;
use App\Traits\GeneratesSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Ingredient extends Model
{
    use HasFactory, SoftDeletes, GeneratesSlug;
    
    protected $fillable = [
        'external_id',
        'source',
        'class',
        'name',
        'slug',
        'description',
        'default_amount',
        'default_amount_unit_id',
        'brand_id'
    ];

    protected $casts = [
        'default_amount' => 'double',
        'sync_status'    => SyncStatus::class,
    ];

    protected static function booted()
    {
        static::created(function (Ingredient $ingredient) {
            $ingredient->loadForSearch();
            SyncIngredientToSearch::dispatch($ingredient, 'insert')->onQueue('ingredients');
        });

        static::updated(function (Ingredient $ingredient) {
            $changed = array_diff(array_keys($ingredient->getChanges()), ['sync_status', 'updated_at']);
            if (empty($changed)) {
                return;
            }
            DB::table('ingredients')->where('id', $ingredient->id)->update(['sync_status' => SyncStatus::Pending->value]);
            $ingredient->loadForSearch();
            SyncIngredientToSearch::dispatch($ingredient, 'update')->onQueue('ingredients');
        });

        static::deleting(function (Ingredient $ingredient) {
            if ($ingredient->isForceDeleting()) {
                $ingredient->nutrients()->detach();
            }
        });

        static::deleted(function (Ingredient $ingredient) {
            DB::table('ingredients')->where('id', $ingredient->id)->update(['sync_status' => SyncStatus::Pending->value]);
            SyncIngredientToSearch::dispatch((object)['id' => $ingredient->id], 'delete')->onQueue('ingredients');
        });

        static::restored(function (Ingredient $ingredient) {
            DB::table('ingredients')->where('id', $ingredient->id)->update(['sync_status' => SyncStatus::Pending->value]);
            $ingredient->loadForSearch();
            SyncIngredientToSearch::dispatch($ingredient, 'insert')->onQueue('ingredients');
        });
    }

    public function nutrients(): BelongsToMany
    {
        return $this->belongsToMany(Nutrient::class, 'ingredient_nutrient')
            ->using(IngredientNutrientPivot::class)
            ->withPivot(['amount', 'amount_unit_id'])
            ->withTimestamps();
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
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
            'brand',
            'default_amount_unit',
            'nutrients',
            'nutrition_facts',
            'categories'
        ]);

        // Then explicitly eager load pivot relationships for nutrients
        $this->nutrients->each(function ($nutrient) {
            $nutrient->pivot->load(['amount_unit']);
        });

        $this->nutrients->makeHidden(['description']);

        return $this;
    }

}
