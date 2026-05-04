<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Ingredient',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'external_id', type: 'string', nullable: true, example: '321360'),
        new OA\Property(property: 'source', type: 'string', nullable: true, example: 'USDA'),
        new OA\Property(property: 'class', type: 'string', nullable: true, example: 'FoodItem'),
        new OA\Property(property: 'name', type: 'string', example: 'Broccoli, raw'),
        new OA\Property(property: 'slug', type: 'string', example: 'broccoli-raw'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'default_amount', type: 'number', format: 'float', nullable: true, example: 100.0),
        new OA\Property(property: 'sync_status', type: 'string', example: 'synced'),
        new OA\Property(property: 'brand', ref: '#/components/schemas/Brand', nullable: true),
        new OA\Property(property: 'default_amount_unit', ref: '#/components/schemas/Unit', nullable: true),
        new OA\Property(
            property: 'nutrients',
            type: 'array',
            nullable: true,
            items: new OA\Items(
                allOf: [
                    new OA\Schema(ref: '#/components/schemas/Nutrient'),
                    new OA\Schema(properties: [
                        new OA\Property(property: 'pivot', ref: '#/components/schemas/IngredientNutrientPivot'),
                    ]),
                ]
            )
        ),
        new OA\Property(property: 'nutrition_facts', type: 'array', nullable: true, items: new OA\Items(ref: '#/components/schemas/IngredientNutritionFact')),
        new OA\Property(property: 'categories', type: 'array', nullable: true, items: new OA\Items(ref: '#/components/schemas/IngredientCategory')),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
class IngredientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'external_id'    => $this->external_id,
            'source'         => $this->source,
            'class'          => $this->class,
            'name'           => $this->name,
            'slug'           => $this->slug,
            'description'    => $this->description,
            'default_amount' => $this->default_amount,
            'sync_status'    => $this->sync_status,
            'brand'               => $this->whenLoaded('brand', fn () => new BrandResource($this->brand)),
            'default_amount_unit' => $this->whenLoaded('default_amount_unit', fn () => new UnitResource($this->default_amount_unit)),
            'nutrients'       => $this->whenLoaded('nutrients', function () {
                return $this->nutrients->map(fn ($nutrient) => array_merge(
                    (new NutrientResource($nutrient))->resolve(request()),
                    ['pivot' => (new IngredientNutrientPivotResource($nutrient->pivot))->resolve(request())],
                ));
            }),
            'nutrition_facts' => $this->whenLoaded('nutrition_facts', fn () => IngredientNutritionFactResource::collection($this->nutrition_facts)),
            'categories'      => $this->whenLoaded('categories', fn () => IngredientCategoryResource::collection($this->categories)),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
            'deleted_at'     => $this->deleted_at,
        ];
    }
}
