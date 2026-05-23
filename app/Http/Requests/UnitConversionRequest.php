<?php

namespace App\Http\Requests;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;

class UnitConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fromUnit = Unit::find($this->input('from_unit_id'));
        $isIU     = $fromUnit && $fromUnit->abbreviation === 'IU';

        return [
            'value'        => ['required', 'numeric'],
            'from_unit_id' => ['required', 'integer', 'exists:units,id'],
            'to_unit_id'   => $isIU
                ? ['nullable', 'integer', 'exists:units,id']
                : ['required', 'integer', 'exists:units,id'],
            'nutrient_id'  => $isIU
                ? ['required', 'integer', 'exists:nutrients,id']
                : ['nullable', 'integer', 'exists:nutrients,id'],
        ];
    }
}
