<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
