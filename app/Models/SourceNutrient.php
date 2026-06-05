<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SourceNutrient extends Model
{
    use HasFactory;
    protected $fillable = [
        'source_id',
        'external_id',
        'name',
        'description',
        'canonical_unit_id',
        'nutrient_id',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function nutrient(): BelongsTo
    {
        return $this->belongsTo(Nutrient::class);
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'ingredient_source_nutrient')
            ->withPivot(['amount', 'amount_unit_id'])
            ->withTimestamps();
    }

    public function isResolved(): bool
    {
        return $this->nutrient_id !== null;
    }
}
