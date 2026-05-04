<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'IngredientNutritionFact',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'category', type: 'string', nullable: true, example: 'Vitamins'),
        new OA\Property(property: 'name', type: 'string', example: 'Vitamin C'),
        new OA\Property(property: 'amount', type: 'number', format: 'float', nullable: true, example: 52.0),
        new OA\Property(property: 'unit', ref: '#/components/schemas/Unit', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
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
