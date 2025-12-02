<?php

namespace App\Models;

use App\Jobs\SyncNutrientToSearch;
use App\Models\IngredientNutrientPivot;
use Illuminate\Database\Eloquent\Model;
use App\Exceptions\NutrientAttachedException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Nutrient extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'nutrients';

    protected $fillable = [
        'source',
        'external_id',
        'name',
        'description',
        'derivation_code',
        'derivation_description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
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

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'ingredient_nutrient')
            ->using(IngredientNutrientPivot::class)
            ->withPivot(['amount', 'amount_unit_id', 'portion_amount', 'portion_amount_unit_id'])
            ->withTimestamps();
    }
}
