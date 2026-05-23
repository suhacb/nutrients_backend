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
