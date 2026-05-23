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
            if ($source->sourceMappings()->exists()) {
                throw new SourceHasNutrientsException();
            }
        });
    }

    public function sourceMappings(): HasMany
    {
        return $this->hasMany(NutrientSourcePivot::class);
    }

    public function nutrients(): HasManyThrough
    {
        return $this->hasManyThrough(Nutrient::class, NutrientSourcePivot::class, 'source_id', 'id', 'id', 'nutrient_id');
    }
}
