<?php

namespace App\Http\Requests;

use App\Http\Requests\DynamicRequest;

class UnitRequest extends DynamicRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function rulesForStore(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'abbreviation' => ['sometimes', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function messagesForStore(): array
    {
        return [
            'name.required' => 'The unit name is required.',
            'name.string' => 'The unit name must be a string.',
            'name.max' => 'The unit name may not be greater than 255 characters.',

            'abbreviation.required' => 'The unit abbreviation is required.',
            'abbreviation.string' => 'The unit abbreviation must be a string.',
            'abbreviation.max' => 'The unit abbreviation may not be greater than 255 characters.',

            'type.string' => 'The type must be a string.',
            'type.max' => 'The type may not be greater than 255 characters.',
        ];
    }

    protected function messagesForUpdate(): array
    {
        return [
            'name.string' => 'The unit name must be a string.',
            'name.max' => 'The unit name may not be greater than 255 characters.',

            'abbreviation.string' => 'The unit abbreviation must be a string.',
            'abbreviation.max' => 'The unit abbreviation may not be greater than 255 characters.',

            'type.string' => 'The type must be a string.',
            'type.max' => 'The type may not be greater than 255 characters.',
        ];
    }
}
