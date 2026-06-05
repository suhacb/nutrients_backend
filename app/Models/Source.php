<?php

namespace App\Models;

use App\Exceptions\SourceHasNutrientsException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Source extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'url',
        'description',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Source $source) {
            if ($source->sourceNutrients()->exists()) {
                throw new SourceHasNutrientsException();
            }
        });
    }

    public function sourceNutrients(): HasMany
    {
        return $this->hasMany(SourceNutrient::class);
    }

    public function nutrients(): HasManyThrough
    {
        return $this->hasManyThrough(Nutrient::class, SourceNutrient::class, 'source_id', 'id', 'id', 'nutrient_id');
    }
}
