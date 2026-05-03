<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResourceSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query' => 'required|string|max:255',
            'page'  => 'sometimes|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'query.required' => 'Search query is required.',
        ];
    }

    public function page(): int
    {
        return (int) $this->input('page', 1);
    }
}
