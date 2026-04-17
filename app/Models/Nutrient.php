<?php

namespace App\Models;

use App\Jobs\SyncNutrientToSearch;
use App\Models\IngredientNutrientPivot;
use App\Models\NutrientTag;
use App\Models\Source;
use Illuminate\Database\Eloquent\Model;
use App\Exceptions\NutrientAttachedException;
use App\Exceptions\NutrientHasChildrenException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nutrient extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'nutrients';

    protected $fillable = [
        'source_id',
        'external_id',
        'name',
        'description',
        'parent_id',
        'slug',
        'canonical_unit_id',
        'iu_to_canonical_factor',
        'is_label_standard',
        'display_order',
    ];

    protected $casts = [
        'created_at'             => 'datetime',
        'updated_at'             => 'datetime',
        'deleted_at'             => 'datetime',
        'is_label_standard'      => 'boolean',
        'iu_to_canonical_factor' => 'decimal:6',
    ];

    protected static function booted()
    {
        static::created(function (Nutrient $nutrient) {
            SyncNutrientToSearch::dispatch($nutrient, 'insert')->onQueue('nutrients');
        });

        static::updated(function (Nutrient $nutrient) {
            SyncNutrientToSearch::dispatch($nutrient, 'update')->onQueue('nutrients');
        });

        static::deleting(function (Nutrient $nutrient) {
            if ($nutrient->children()->exists()) {
                throw new NutrientHasChildrenException("Cannot delete nutrient: it has one or more non-deleted children.");
            }

            if ($nutrient->ingredients()->exists()) {
                throw new NutrientAttachedException("Cannot delete nutrient: it is attached to one or more ingredients.");
            }
        });

        static::deleted(function (Nutrient $nutrient) {
            SyncNutrientToSearch::dispatch($nutrient, 'delete')->onQueue('nutrients');
        });

        static::restored(function (Nutrient $nutrient) {
            SyncNutrientToSearch::dispatch($nutrient, 'insert')->onQueue('nutrients');
        });
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Nutrient::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Nutrient::class, 'parent_id');
    }

    public function canonicalUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'canonical_unit_id');
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'ingredient_nutrient')
            ->using(IngredientNutrientPivot::class)
            ->withPivot(['amount', 'amount_unit_id'])
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(NutrientTag::class, 'nutrient_nutrient_tag');
    }
}
