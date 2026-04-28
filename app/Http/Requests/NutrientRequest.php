<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class NutrientRequest extends DynamicRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function rulesForStore(): array
    {
        return [
            'source_id'              => ['required', 'integer', 'exists:sources,id'],
            'external_id'            => ['sometimes', 'nullable', 'string', 'max:255',
                function ($_, $value, $fail) {
                    $exists = \App\Models\Nutrient::where('source_id', $this->input('source_id'))
                        ->where('external_id', $value)
                        ->where('name', $this->input('name'))
                        ->exists();
                    if ($exists) {
                        $fail('The combination of source, external_id, and name must be unique.');
                    }
                },
            ],
            'name'                   => ['required', 'string', 'max:255'],
            'description'            => ['sometimes', 'nullable', 'string'],
            'parent_id'              => ['sometimes', 'nullable', 'integer', 'exists:nutrients,id'],
            'slug'                   => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('nutrients', 'slug')],
            'canonical_unit_id'      => ['sometimes', 'nullable', 'integer', 'exists:units,id'],
            'iu_to_canonical_factor' => ['sometimes', 'nullable', 'numeric'],
            'is_label_standard'      => ['sometimes', 'boolean'],
            'display_order'          => ['sometimes', 'nullable', 'integer'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        $nutrient = $this->route('nutrient');

        return [
            'source_id'              => ['sometimes', 'integer', 'exists:sources,id'],
            'external_id'            => ['sometimes', 'nullable', 'string', 'max:255',
                function ($_, $value, $fail) {
                    $sourceId = $this->input('source_id', $this->route('nutrient')?->source_id);
                    $exists = \App\Models\Nutrient::where('source_id', $sourceId)
                        ->where('external_id', $value)
                        ->where('name', $this->input('name', $this->route('nutrient')?->name))
                        ->where('id', '!=', $this->route('nutrient')?->id)
                        ->exists();
                    if ($exists) {
                        $fail('The combination of source, external_id, and name must be unique.');
                    }
                },
            ],
            'name'                   => ['sometimes', 'string', 'max:255'],
            'description'            => ['sometimes', 'nullable', 'string'],
            'parent_id'              => ['sometimes', 'nullable', 'integer', 'exists:nutrients,id'],
            'slug'                   => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('nutrients', 'slug')->ignore($nutrient)],
            'canonical_unit_id'      => ['sometimes', 'nullable', 'integer', 'exists:units,id'],
            'iu_to_canonical_factor' => ['sometimes', 'nullable', 'numeric'],
            'is_label_standard'      => ['sometimes', 'boolean'],
            'display_order'          => ['sometimes', 'nullable', 'integer'],
        ];
    }

    protected function messagesForStore(): array
    {
        return [
            'source_id.required' => 'A source is required.',
            'source_id.integer'  => 'The source must be a numeric ID.',
            'source_id.exists'   => 'The selected source does not exist.',

            'external_id.string' => 'The external ID must be a string.',
            'external_id.max'    => 'The external ID may not exceed 255 characters.',

            'name.required' => 'The nutrient name is required.',
            'name.string'   => 'The nutrient name must be a string.',
            'name.max'      => 'The nutrient name may not exceed 255 characters.',

            'description.string' => 'The description must be a string.',

            'parent_id.integer' => 'The parent must be a numeric ID.',
            'parent_id.exists'  => 'The selected parent nutrient does not exist.',

            'slug.string' => 'The slug must be a string.',
            'slug.max'    => 'The slug may not exceed 255 characters.',
            'slug.regex'  => 'The slug may only contain lowercase letters, numbers, and hyphens.',
            'slug.unique' => 'A nutrient with this slug already exists.',

            'canonical_unit_id.integer' => 'The canonical unit must be a numeric ID.',
            'canonical_unit_id.exists'  => 'The selected canonical unit does not exist.',

            'iu_to_canonical_factor.numeric' => 'The IU conversion factor must be a number.',

            'is_label_standard.boolean' => 'The label standard flag must be true or false.',

            'display_order.integer' => 'The display order must be an integer.',
        ];
    }

    protected function messagesForUpdate(): array
    {
        return [
            'source_id.integer' => 'The source must be a numeric ID.',
            'source_id.exists'  => 'The selected source does not exist.',

            'external_id.string' => 'The external ID must be a string.',
            'external_id.max'    => 'The external ID may not exceed 255 characters.',

            'name.string' => 'The nutrient name must be a string.',
            'name.max'    => 'The nutrient name may not exceed 255 characters.',

            'description.string' => 'The description must be a string.',

            'parent_id.integer' => 'The parent must be a numeric ID.',
            'parent_id.exists'  => 'The selected parent nutrient does not exist.',

            'slug.string' => 'The slug must be a string.',
            'slug.max'    => 'The slug may not exceed 255 characters.',
            'slug.regex'  => 'The slug may only contain lowercase letters, numbers, and hyphens.',
            'slug.unique' => 'A nutrient with this slug already exists.',

            'canonical_unit_id.integer' => 'The canonical unit must be a numeric ID.',
            'canonical_unit_id.exists'  => 'The selected canonical unit does not exist.',

            'iu_to_canonical_factor.numeric' => 'The IU conversion factor must be a number.',

            'is_label_standard.boolean' => 'The label standard flag must be true or false.',

            'display_order.integer' => 'The display order must be an integer.',
        ];
    }
}