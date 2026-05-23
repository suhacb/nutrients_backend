<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutrientMappingReview extends Model
{
    protected $fillable = [
        'nutrient_id',
        'suggested_canonical_id',
        'confidence',
        'decision_type',
        'reasoning',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'confidence'  => 'integer',
        'nutrient_id' => 'integer',
    ];

    public function nutrient(): BelongsTo
    {
        return $this->belongsTo(Nutrient::class);
    }

    public function suggestedCanonical(): BelongsTo
    {
        return $this->belongsTo(Nutrient::class, 'suggested_canonical_id');
    }
}
