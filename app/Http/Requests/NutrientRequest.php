<?php

namespace App\Http\Requests;

use App\Models\Nutrient;
use App\Http\Requests\DynamicRequest;
use Illuminate\Validation\Rule;

class NutrientRequest extends DynamicRequest
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
            'source' => ['required', 'string', 'max:255'],
            'external_id' => ['sometimes', 'string', 'max:255',
                // Unique per source + external_id + name
                function ($attribute, $value, $fail) {
                    $source = $this->input('source');
                    $name = $this->input('name');
                    $exists = Nutrient::where('source', $source)
                        ->where('external_id', $value)
                        ->where('name', $name)
                        ->exists();
                    if ($exists) {
                        $fail('The combination of source, external_id, and name must be unique.');
                    }
                }
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'derivation_code' => ['sometimes', 'string', 'max:255'],
            'derivation_description' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'integer', 'exists:nutrients,id'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('nutrients', 'slug')],
            'canonical_unit_id' => ['sometimes', 'integer', 'exists:units,id'],
            'iu_to_canonical_factor' => ['sometimes', 'numeric'],
            'is_label_standard' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        return [
            'source' => ['sometimes', 'string', 'max:255'],
            'external_id' => ['sometimes', 'string', 'max:255',
                // Unique per source + external_id + name
                function ($attribute, $value, $fail) {
                    $source = $this->input('source');
                    $name = $this->input('name');
                    $exists = Nutrient::where('source', $source)
                        ->where('external_id', $value)
                        ->where('name', $name)
                        ->exists();
                    if ($exists) {
                        $fail('The combination of source, external_id, and name must be unique.');
                    }
                }
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'derivation_code' => ['sometimes', 'string', 'max:255'],
            'derivation_description' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'integer', 'exists:nutrients,id'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('nutrients', 'slug')->ignore($this->nutrient)],
            'canonical_unit_id' => ['sometimes', 'integer', 'exists:units,id'],
            'iu_to_canonical_factor' => ['sometimes', 'numeric'],
            'is_label_standard' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer'],
        ];
    }

    protected function messagesForStore(): array
    {
        return [
            'source.required' => 'The source field is required.',
            'source.string' => 'The source must be a string.',
            'source.max' => 'The source may not be greater than 255 characters.',

            'external_id.string' => 'The external ID must be a string.',
            'external_id.max' => 'The external ID may not be greater than 255 characters.',

            'name.required' => 'The nutrient name is required.',
            'name.string' => 'The nutrient name must be a string.',
            'name.max' => 'The nutrient name may not be greater than 255 characters.',

            'description.string' => 'The description must be a string.',

            'derivation_code.string' => 'The derivation code must be a string.',
            'derivation_code.max' => 'The derivation code may not be greater than 255 characters.',

            'derivation_description.string' => 'The derivation description must be a string.',
            'derivation_description.max' => 'The derivation description may not be greater than 255 characters.',
        ];
    }

    protected function messagesForUpdate(): array
    {
        return [
            'source.string' => 'The source must be a string.',
            'source.max' => 'The source may not be greater than 255 characters.',

            'external_id.string' => 'The external ID must be a string.',
            'external_id.max' => 'The external ID may not be greater than 255 characters.',

            'name.string' => 'The nutrient name must be a string.',
            'name.max' => 'The nutrient name may not be greater than 255 characters.',

            'description.string' => 'The description must be a string.',

            'derivation_code.string' => 'The derivation code must be a string.',
            'derivation_code.max' => 'The derivation code may not be greater than 255 characters.',

            'derivation_description.string' => 'The derivation description must be a string.',
            'derivation_description.max' => 'The derivation description may not be greater than 255 characters.',
        ];
    }
}
