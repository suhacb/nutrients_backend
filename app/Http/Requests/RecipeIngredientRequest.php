<?php

namespace App\Http\Requests;

class RecipeIngredientRequest extends DynamicRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function rulesForAttach(): array
    {
        return [
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'amount'        => ['required', 'numeric', 'min:0.0001'],
            'unit_id'       => ['required', 'integer', 'exists:units,id'],
        ];
    }

    protected function rulesForUpdatePivot(): array
    {
        return [
            'amount'  => ['sometimes', 'numeric', 'min:0.0001'],
            'unit_id' => ['sometimes', 'integer', 'exists:units,id'],
        ];
    }
}
