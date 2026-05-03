<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngredientNutritionFactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'category'   => $this->category,
            'name'       => $this->name,
            'amount'     => $this->amount,
            'unit'       => $this->whenLoaded('unit', fn () => new UnitResource($this->unit)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
