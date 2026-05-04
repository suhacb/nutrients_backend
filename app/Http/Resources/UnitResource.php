<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Unit',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Gram'),
        new OA\Property(property: 'abbreviation', type: 'string', example: 'g'),
        new OA\Property(property: 'type', type: 'string', nullable: true, example: 'mass'),
        new OA\Property(property: 'to_base_factor', type: 'number', format: 'float', nullable: true, example: 1.0),
        new OA\Property(property: 'base_unit_id', type: 'integer', nullable: true, example: null),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class UnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'abbreviation'   => $this->abbreviation,
            'type'           => $this->type,
            'to_base_factor' => $this->to_base_factor,
            'base_unit_id'   => $this->base_unit_id,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
