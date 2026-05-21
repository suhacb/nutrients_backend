<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Nutrient',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'external_id', type: 'string', nullable: true, example: '1004'),
        new OA\Property(property: 'name', type: 'string', example: 'Vitamin C'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'slug', type: 'string', example: 'vitamin-c'),
        new OA\Property(property: 'iu_to_canonical_factor', type: 'number', format: 'float', nullable: true, example: null),
        new OA\Property(property: 'is_label_standard', type: 'boolean', example: true),
        new OA\Property(property: 'display_order', type: 'integer', nullable: true, example: 10),
        new OA\Property(property: 'sync_status', type: 'string', example: 'synced'),
        new OA\Property(property: 'source', ref: '#/components/schemas/Source', nullable: true),
        new OA\Property(property: 'canonical_unit', ref: '#/components/schemas/Unit', nullable: true),
        new OA\Property(property: 'parent', ref: '#/components/schemas/Nutrient', nullable: true),
        new OA\Property(property: 'children', type: 'array', items: new OA\Items(ref: '#/components/schemas/Nutrient')),
        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(ref: '#/components/schemas/NutrientTag')),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
class NutrientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'external_id'            => $this->external_id,
            'name'                   => $this->name,
            'description'            => $this->description,
            'slug'                   => $this->slug,
            'iu_to_canonical_factor' => $this->iu_to_canonical_factor,
            'is_label_standard'      => $this->is_label_standard,
            'display_order'          => $this->display_order,
            'sync_status'            => $this->sync_status,
            'source'         => $this->whenLoaded('source', fn () => $this->source ? new SourceResource($this->source) : null),
            'canonical_unit' => $this->whenLoaded('canonicalUnit', fn () => $this->canonicalUnit ? new UnitResource($this->canonicalUnit) : null),
            'parent'         => $this->whenLoaded('parent', fn () => $this->parent ? new NutrientResource($this->parent) : null),
            'children' => $this->whenLoaded('children', fn () => NutrientResource::collection($this->children)),
            'tags'     => $this->whenLoaded('tags', fn () => NutrientTagResource::collection($this->tags)),
            'created_at'             => $this->created_at,
            'updated_at'             => $this->updated_at,
            'deleted_at'             => $this->deleted_at,
        ];
    }
}
