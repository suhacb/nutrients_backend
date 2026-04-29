<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class BrandRequest extends DynamicRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function rulesForStore(): array
    {
        return [
            'name'        => ['required', 'string'],
            'owner'       => ['required', 'string'],
            'slug'        => ['required', 'string', 'max:255', Rule::unique('brands', 'slug')],
            'country'     => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        $brand = $this->route('brand');

        return [
            'name'        => ['sometimes', 'string'],
            'owner'       => ['sometimes', 'string'],
            'slug'        => ['sometimes', 'string', 'max:255', Rule::unique('brands', 'slug')->ignore($brand)],
            'country'     => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    protected function messagesForStore(): array
    {
        return [
            'name.required'  => 'The brand name is required.',
            'name.string'    => 'The brand name must be a string.',
            'owner.required' => 'The brand owner is required.',
            'owner.string'   => 'The brand owner must be a string.',
            'slug.required'  => 'The slug is required.',
            'slug.string'    => 'The slug must be a string.',
            'slug.max'       => 'The slug may not be greater than 255 characters.',
            'slug.unique'    => 'A brand with this slug already exists.',
            'country.string' => 'The country must be a string.',
            'country.max'    => 'The country may not be greater than 255 characters.',
            'description.string' => 'The description must be a string.',
        ];
    }

    protected function messagesForUpdate(): array
    {
        return [
            'name.string'    => 'The brand name must be a string.',
            'owner.string'   => 'The brand owner must be a string.',
            'slug.string'    => 'The slug must be a string.',
            'slug.max'       => 'The slug may not be greater than 255 characters.',
            'slug.unique'    => 'A brand with this slug already exists.',
            'country.string' => 'The country must be a string.',
            'country.max'    => 'The country may not be greater than 255 characters.',
            'description.string' => 'The description must be a string.',
        ];
    }
}
