<?php

namespace App\Models;

use App\Services\NutrientMergeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutrientMappingReview extends Model
{
    protected $fillable = [
        'source_nutrient_id',
        'suggested_canonical_id',
        'confidence',
        'decision_type',
        'reasoning',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at'        => 'datetime',
        'confidence'         => 'integer',
        'source_nutrient_id' => 'integer',
    ];

    public function sourceNutrient(): BelongsTo
    {
        return $this->belongsTo(SourceNutrient::class);
    }

    public function suggestedCanonical(): BelongsTo
    {
        return $this->belongsTo(Nutrient::class, 'suggested_canonical_id');
    }

    public function executeMerge(?Nutrient $overrideCanonical = null): void
    {
        $canonical = $overrideCanonical ?? $this->suggestedCanonical;

        NutrientMergeService::merge($this->sourceNutrient, $canonical);

        $this->update([
            'suggested_canonical_id' => $canonical->id,
            'decision_type'          => 'merge',
            'status'                 => 'approved',
            'resolved_at'            => now(),
        ]);
    }

    public function executeParent(?Nutrient $overrideCanonical = null): void
    {
        $canonical = $overrideCanonical ?? $this->suggestedCanonical;

        NutrientMergeService::promote($this->sourceNutrient, parentId: $canonical->id);

        $this->update([
            'suggested_canonical_id' => $canonical->id,
            'decision_type'          => 'parent',
            'status'                 => 'approved',
            'resolved_at'            => now(),
        ]);
    }

    public function executeKeep(): void
    {
        NutrientMergeService::promote($this->sourceNutrient);

        $this->update([
            'decision_type' => 'keep',
            'status'        => 'approved',
            'resolved_at'   => now(),
        ]);
    }

    public function executeReject(): void
    {
        $this->update([
            'status'      => 'rejected',
            'resolved_at' => now(),
        ]);
    }
}
