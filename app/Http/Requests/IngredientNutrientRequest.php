<?php

namespace App\Http\Requests;

class IngredientNutrientRequest extends DynamicRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function rulesForAttach(): array
    {
        return [
            'nutrient_id'    => ['required', 'integer', 'exists:nutrients,id'],
            'amount'         => ['sometimes', 'numeric', 'min:0'],
            'amount_unit_id' => ['sometimes', 'integer', 'exists:units,id'],
        ];
    }

    protected function rulesForUpdatePivot(): array
    {
        return [
            'amount'         => ['sometimes', 'numeric', 'min:0'],
            'amount_unit_id' => ['sometimes', 'integer', 'exists:units,id'],
        ];
    }

    protected function rulesForDetachAll(): array
    {
        return [
            'nutrient_ids'   => ['sometimes', 'array', 'min:1'],
            'nutrient_ids.*' => ['integer', 'exists:nutrients,id'],
        ];
    }
}
