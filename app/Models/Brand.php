<?php

namespace App\Models;

use App\Exceptions\BrandHasIngredientsException;
use App\Jobs\SyncIngredientToSearch;
use App\Traits\GeneratesSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasFactory, SoftDeletes, GeneratesSlug;

    protected $fillable = [
        'name',
        'owner',
        'slug',
        'country',
        'description',
    ];

    protected static function booted(): void
    {
        static::saved(function (Brand $brand) {
            $brand->ingredients()
                ->select('id')
                ->each(function (Ingredient $ingredient) {
                    $ingredient->loadForSearch();
                    SyncIngredientToSearch::dispatch($ingredient, 'update')->onQueue('ingredients');
                });
        });

        static::deleting(function (Brand $brand) {
            if ($brand->isForceDeleting() && $brand->ingredients()->exists()) {
                throw new BrandHasIngredientsException();
            }
        });
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }
}
