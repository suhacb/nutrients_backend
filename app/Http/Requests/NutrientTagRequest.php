<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class NutrientTagRequest extends DynamicRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function rulesForStore(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'slug'        => ['required', 'string', 'max:255', Rule::unique('nutrient_tags', 'slug')],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'max:255'],
            'slug'        => ['sometimes', 'string', 'max:255', Rule::unique('nutrient_tags', 'slug')->ignore($this->route('nutrientTag'))],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }

    protected function messagesForStore(): array
    {
        return [
            'name.required' => 'The tag name is required.',
            'name.string'   => 'The tag name must be a string.',
            'name.max'      => 'The tag name may not exceed 255 characters.',

            'slug.required' => 'The slug is required.',
            'slug.string'   => 'The slug must be a string.',
            'slug.max'      => 'The slug may not exceed 255 characters.',
            'slug.unique'   => 'A tag with this slug already exists.',

            'description.string' => 'The description must be a string.',
        ];
    }

    protected function messagesForUpdate(): array
    {
        return [
            'name.string' => 'The tag name must be a string.',
            'name.max'    => 'The tag name may not exceed 255 characters.',

            'slug.string' => 'The slug must be a string.',
            'slug.max'    => 'The slug may not exceed 255 characters.',
            'slug.unique' => 'A tag with this slug already exists.',

            'description.string' => 'The description must be a string.',
        ];
    }
}