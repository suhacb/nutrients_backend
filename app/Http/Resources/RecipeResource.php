<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Recipe',
    type: 'object',
    properties: [
        new OA\Property(property: 'id',           type: 'integer',  example: 1),
        new OA\Property(property: 'name',          type: 'string',   example: 'Pasta Bolognese'),
        new OA\Property(property: 'slug',          type: 'string',   example: 'pasta-bolognese'),
        new OA\Property(property: 'description',   type: 'string',   nullable: true, example: 'A classic Italian dish.'),
        new OA\Property(property: 'instructions',  type: 'string',   nullable: true, example: "## Method\n1. Cook pasta."),
        new OA\Property(property: 'portions',      type: 'integer',  example: 4),
        new OA\Property(property: 'source_url',    type: 'string',   nullable: true, example: 'https://example.com/pasta'),
        new OA\Property(property: 'sync_status',   type: 'string',   enum: ['pending', 'synced', 'failed'], example: 'synced'),
        new OA\Property(property: 'diet_tags',     type: 'array',    items: new OA\Items(ref: '#/components/schemas/DietTag')),
        new OA\Property(property: 'ingredients',   type: 'array',    items: new OA\Items(type: 'object')),
        new OA\Property(property: 'created_at',    type: 'string',   format: 'date-time'),
        new OA\Property(property: 'updated_at',    type: 'string',   format: 'date-time'),
        new OA\Property(property: 'deleted_at',    type: 'string',   format: 'date-time', nullable: true),
    ]
)]
class RecipeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'slug'         => $this->slug,
            'description'  => $this->description,
            'instructions' => $this->instructions,
            'portions'     => $this->portions,
            'source_url'   => $this->source_url,
            'sync_status'  => $this->sync_status,
            'diet_tags'    => DietTagResource::collection($this->whenLoaded('dietTags')),
            'ingredients'  => $this->whenLoaded('ingredients'),
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
            'deleted_at'   => $this->deleted_at,
        ];
    }
}
