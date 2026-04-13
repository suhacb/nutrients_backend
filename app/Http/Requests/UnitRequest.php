<?php

namespace App\Http\Requests;

use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends DynamicRequest
{
    private const TYPES = ['mass', 'energy', 'volume', 'other'];
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
            'name'           => ['required', 'string', 'max:255', Rule::unique('units')->where(fn ($q) => $q
                ->where('abbreviation', $this->input('abbreviation'))
                ->where('type', $this->input('type')))],
            'abbreviation'   => ['required', 'string', 'max:255'],
            'type'           => ['nullable', Rule::in(self::TYPES)],
            'base_unit_id'   => ['nullable', 'integer', 'exists:units,id'],
            'to_base_factor' => ['nullable', 'numeric'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        return [
            'name'           => ['sometimes', 'string', 'max:255'],
            'abbreviation'   => ['sometimes', 'string', 'max:255'],
            'type'           => ['nullable', Rule::in(self::TYPES)],
            'base_unit_id'   => ['nullable', 'integer', 'exists:units,id'],
            'to_base_factor' => ['nullable', 'numeric'],
        ];
    }

    protected function messagesForStore(): array
    {
        return [
            'name.required'         => 'The unit name is required.',
            'name.string'           => 'The unit name must be a string.',
            'name.max'              => 'The unit name may not be greater than 255 characters.',
            'name.unique'           => 'A unit with this name, abbreviation, and type already exists.',

            'abbreviation.required' => 'The unit abbreviation is required.',
            'abbreviation.string'   => 'The unit abbreviation must be a string.',
            'abbreviation.max'      => 'The unit abbreviation may not be greater than 255 characters.',

            'type.in'               => 'The type must be one of: mass, energy, volume, other.',

            'base_unit_id.exists'   => 'The selected base unit does not exist.',
            'to_base_factor.numeric' => 'The conversion factor must be a number.',
        ];
    }

    protected function messagesForUpdate(): array
    {
        return [
            'name.string'            => 'The unit name must be a string.',
            'name.max'               => 'The unit name may not be greater than 255 characters.',

            'abbreviation.string'    => 'The unit abbreviation must be a string.',
            'abbreviation.max'       => 'The unit abbreviation may not be greater than 255 characters.',

            'type.in'                => 'The type must be one of: mass, energy, volume, other.',

            'base_unit_id.exists'    => 'The selected base unit does not exist.',
            'to_base_factor.numeric' => 'The conversion factor must be a number.',
        ];
    }
}
