<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'source'         => $this->whenLoaded('source', fn () => new SourceResource($this->source)),
            'canonical_unit' => $this->whenLoaded('canonicalUnit', fn () => new UnitResource($this->canonicalUnit)),
            'parent'         => $this->whenLoaded('parent', fn () => new NutrientResource($this->parent)),
            'children' => $this->whenLoaded('children', fn () => NutrientResource::collection($this->children)),
            'tags'     => $this->whenLoaded('tags', fn () => NutrientTagResource::collection($this->tags)),
            'created_at'             => $this->created_at,
            'updated_at'             => $this->updated_at,
            'deleted_at'             => $this->deleted_at,
        ];
    }
}
