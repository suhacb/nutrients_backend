<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class DietTagRequest extends DynamicRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function rulesForStore(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'slug'        => ['sometimes', 'string', 'max:255', Rule::unique('diet_tags', 'slug')],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'max:255'],
            'slug'        => ['sometimes', 'string', 'max:255', Rule::unique('diet_tags', 'slug')->ignore($this->route('dietTag'))],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
