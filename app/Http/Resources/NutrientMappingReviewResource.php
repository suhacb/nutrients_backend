<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NutrientMappingReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'status'              => $this->status,
            'confidence'          => $this->confidence,
            'decision_type'       => $this->decision_type,
            'reasoning'           => $this->reasoning,
            'resolved_at'         => $this->resolved_at?->toISOString(),
            'source_nutrient'     => $this->whenLoaded('sourceNutrient', fn () => $this->sourceNutrient
                ? ['id' => $this->sourceNutrient->id, 'name' => $this->sourceNutrient->name]
                : null
            ),
            'suggested_canonical' => $this->whenLoaded('suggestedCanonical', fn () => $this->suggestedCanonical
                ? ['id' => $this->suggestedCanonical->id, 'name' => $this->suggestedCanonical->name]
                : null
            ),
        ];
    }
}
