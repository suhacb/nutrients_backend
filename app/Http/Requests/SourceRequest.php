<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SourceRequest extends DynamicRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function rulesForStore(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'slug'        => ['required', 'string', 'max:255', Rule::unique('sources', 'slug')],
            'url'         => ['nullable', 'url'],
            'description' => ['nullable', 'string'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        $source = $this->route('source');

        return [
            'name'        => ['sometimes', 'string', 'max:255'],
            'slug'        => ['sometimes', 'string', 'max:255', Rule::unique('sources', 'slug')->ignore($source)],
            'url'         => ['nullable', 'url'],
            'description' => ['nullable', 'string'],
        ];
    }

    protected function messagesForStore(): array
    {
        return [
            'name.required' => 'The source name is required.',
            'name.string'   => 'The source name must be a string.',
            'name.max'      => 'The source name may not be greater than 255 characters.',

            'slug.required' => 'The source slug is required.',
            'slug.string'   => 'The source slug must be a string.',
            'slug.max'      => 'The source slug may not be greater than 255 characters.',
            'slug.unique'   => 'A source with this slug already exists.',

            'url.url'             => 'The URL must be a valid URL.',
            'description.string'  => 'The description must be a string.',
        ];
    }

    protected function messagesForUpdate(): array
    {
        return [
            'name.string' => 'The source name must be a string.',
            'name.max'    => 'The source name may not be greater than 255 characters.',

            'slug.string' => 'The source slug must be a string.',
            'slug.max'    => 'The source slug may not be greater than 255 characters.',
            'slug.unique' => 'A source with this slug already exists.',

            'url.url'            => 'The URL must be a valid URL.',
            'description.string' => 'The description must be a string.',
        ];
    }
}