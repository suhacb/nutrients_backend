<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'DietTag',
    type: 'object',
    properties: [
        new OA\Property(property: 'id',          type: 'integer', example: 1),
        new OA\Property(property: 'name',         type: 'string',  example: 'Ketogenic'),
        new OA\Property(property: 'slug',         type: 'string',  example: 'ketogenic'),
        new OA\Property(property: 'description',  type: 'string',  nullable: true, example: 'High fat, very low carbohydrate diet.'),
        new OA\Property(property: 'created_at',   type: 'string',  format: 'date-time'),
        new OA\Property(property: 'updated_at',   type: 'string',  format: 'date-time'),
    ]
)]
class DietTagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
