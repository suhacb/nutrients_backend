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

    public function executeMerge(): void
    {
        $importedId   = $this->nutrient_id;
        $canonicalId  = $this->suggested_canonical_id;

        IngredientNutrientPivot::where('nutrient_id', $importedId)
            ->update(['nutrient_id' => $canonicalId]);

        NutrientSourcePivot::where('nutrient_id', $importedId)
            ->update(['nutrient_id' => $canonicalId]);

        $nutrient = $this->nutrient;
        Nutrient::withoutEvents(fn () => $nutrient->forceDelete());

        $this->update([
            'decision_type' => 'merge',
            'status'        => 'approved',
            'resolved_at'   => now(),
        ]);
    }

    public function executeKeep(): void
    {
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
