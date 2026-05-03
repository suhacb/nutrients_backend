<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngredientNutrientPivotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'amount'      => $this->amount,
            'amount_unit' => $this->whenLoaded('amount_unit', fn () => new UnitResource($this->amount_unit)),
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
