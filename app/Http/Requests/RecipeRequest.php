<?php

namespace App\Http\Requests;

class RecipeRequest extends DynamicRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function rulesForStore(): array
    {
        return [
            'name'         => ['required', 'string'],
            'description'  => ['sometimes', 'nullable', 'string'],
            'instructions' => ['sometimes', 'nullable', 'string'],
            'portions'     => ['required', 'integer', 'min:1'],
            'source_url'   => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        return [
            'name'         => ['sometimes', 'string'],
            'description'  => ['sometimes', 'nullable', 'string'],
            'instructions' => ['sometimes', 'nullable', 'string'],
            'portions'     => ['sometimes', 'integer', 'min:1'],
            'source_url'   => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }
}
