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
        new OA\Property(
            property: 'ingredients',
            type: 'array',
            nullable: true,
            items: new OA\Items(
                allOf: [
                    new OA\Schema(ref: '#/components/schemas/Ingredient'),
                    new OA\Schema(properties: [
                        new OA\Property(
                            property: 'pivot',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'recipe_id',     type: 'integer', example: 1),
                                new OA\Property(property: 'ingredient_id', type: 'integer', example: 1),
                                new OA\Property(property: 'amount',        type: 'number',  format: 'float', example: 200.0),
                                new OA\Property(property: 'unit_id',       type: 'integer', example: 1),
                                new OA\Property(property: 'unit',          ref: '#/components/schemas/Unit', nullable: true),
                                new OA\Property(property: 'created_at',    type: 'string',  format: 'date-time'),
                                new OA\Property(property: 'updated_at',    type: 'string',  format: 'date-time'),
                            ]
                        ),
                    ]),
                ]
            )
        ),
        new OA\Property(
            property: 'nutrient_profile',
            type: 'array',
            nullable: true,
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'nutrient_id',   type: 'integer', example: 1),
                    new OA\Property(property: 'nutrient_name', type: 'string',  example: 'Protein'),
                    new OA\Property(property: 'amount',        type: 'number',  format: 'float', example: 62.0),
                    new OA\Property(property: 'unit_id',       type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'unit',          type: 'string',  nullable: true,  example: 'g'),
                ]
            )
        ),
        new OA\Property(property: 'created_at',   type: 'string',   format: 'date-time'),
        new OA\Property(property: 'updated_at',   type: 'string',   format: 'date-time'),
        new OA\Property(property: 'deleted_at',   type: 'string',   format: 'date-time', nullable: true),
    ]
)]
class RecipeResource extends JsonResource
{
    private ?array $nutrientProfile = null;

    public function withNutrientProfile(array $profile): self
    {
        $this->nutrientProfile = $profile;
        return $this;
    }

    public function toArray(Request $request): array
    {
        $data = [
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

        if ($this->nutrientProfile !== null) {
            $data['nutrient_profile'] = $this->nutrientProfile;
        }

        return $data;
    }
}
