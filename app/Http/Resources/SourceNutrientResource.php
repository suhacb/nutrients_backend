<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SourceNutrient',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'external_id', type: 'string', example: '1004'),
        new OA\Property(property: 'name', type: 'string', example: 'Total lipid (fat)'),
        new OA\Property(property: 'source', ref: '#/components/schemas/Source', nullable: true),
    ]
)]
class SourceNutrientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'external_id' => $this->external_id,
            'name'        => $this->name,
            'source'      => $this->whenLoaded('source', fn () => $this->source ? new SourceResource($this->source) : null),
        ];
    }
}
