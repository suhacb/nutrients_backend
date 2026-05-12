<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
