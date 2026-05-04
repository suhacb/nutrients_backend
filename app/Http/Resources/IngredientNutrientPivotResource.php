<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'IngredientNutrientPivot',
    type: 'object',
    properties: [
        new OA\Property(property: 'amount', type: 'number', format: 'float', nullable: true, example: 10.5),
        new OA\Property(property: 'amount_unit', ref: '#/components/schemas/Unit', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
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
