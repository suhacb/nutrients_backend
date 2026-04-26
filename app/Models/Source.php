<?php

namespace App\Models;

use App\Exceptions\SourceHasNutrientsException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            if ($source->nutrients()->exists()) {
                throw new SourceHasNutrientsException();
            }
        });
    }

    public function nutrients(): HasMany
    {
        return $this->hasMany(Nutrient::class);
    }
}
