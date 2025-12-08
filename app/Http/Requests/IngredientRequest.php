<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Http\Requests\DynamicRequest;
use App\Models\Ingredient;

class IngredientRequest extends DynamicRequest
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
            'external_id' => [
                'nullable',
                'string',
                Rule::unique('ingredients')->where(function ($query) {
                    return $query->where('source', $this->input('source'))
                                 ->where('name', $this->input('name'));
                }),
            ],
            'source' => ['required', 'string', 'max:255'],
            'class' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_amount' => ['required', 'numeric', 'min:0'],
            'default_amount_unit_id' => ['required', 'exists:units,id'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        $ingredientRoute = $this->route('ingredient');

        $ingredientId = $ingredientRoute instanceof Ingredient
            ? $ingredientRoute->id
            : $ingredientRoute; // if it's already the ID

        return [
            'external_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::unique('ingredients')
                    ->where(function ($query) {
                        return $query->where('source', $this->input('source'))
                                     ->where('name', $this->input('name'));
                    })
                    ->ignore($ingredientId),
            ],
            'source' => ['sometimes', 'string', 'max:255'],
            'class' => ['sometimes', 'nullable', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'default_amount' => ['sometimes', 'numeric', 'min:0'],
            'default_amount_unit_id' => ['sometimes', 'exists:units,id'],
        ];
    }

    protected function messagesForStore(): array
    {
        return [
            'external_id.unique' => 'The combination of external ID, source, and name must be unique.',

            'source.required' => 'Source is required.',
            'source.string' => 'Source must be a string.',
            
            'name.required' => 'Name is required.',
            
            'name.string' => 'Name must be a string.',
            
            'default_amount.required' => 'Default amount is required.',
            'default_amount.numeric' => 'Default amount must be a number.',
            'default_amount.min' => 'Default amount must be at least 0.',
            
            'default_amount_unit_id.required' => 'Default amount unit is required.',
            'default_amount_unit_id.exists' => 'Selected default amount unit does not exist.',
        ];
    }

    protected function messagesForUpdate(): array
    {
        return [
            'external_id.unique' => 'The combination of external ID, source, and name must be unique.',

            'source.string' => 'Source must be a string.',
            
            'name.string' => 'Name must be a string.',
            
            'default_amount.numeric' => 'Default amount must be a number.',
            'default_amount.min' => 'Default amount must be at least 0.',
            
            'default_amount_unit_id.exists' => 'Selected default amount unit does not exist.',
        ];
    }
}
