<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class LabelNutrientMapping extends Model
{
    protected $fillable = [
        'label_key',
        'nutrient_id',
        'confidence',
        'reasoning',
        'status',
    ];

    protected $casts = [
        'confidence' => 'integer',
    ];

    public function nutrient(): BelongsTo
    {
        return $this->belongsTo(Nutrient::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }
}
