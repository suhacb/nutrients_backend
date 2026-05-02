<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'prompt.required' => 'A prompt is required.',
            'prompt.max'      => 'The prompt may not exceed 2000 characters.',
        ];
    }
}
