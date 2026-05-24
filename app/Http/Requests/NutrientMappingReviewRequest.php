<?php

namespace App\Http\Requests;

class NutrientMappingReviewRequest extends DynamicRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function rulesForResolve(): array
    {
        return [
            'decision'     => ['required', 'string', 'in:merge,parent,keep,reject'],
            'canonical_id' => ['nullable', 'integer', 'exists:nutrients,id'],
        ];
    }

    protected function messagesForResolve(): array
    {
        return [
            'decision.required' => 'A decision is required.',
            'decision.in'       => 'Decision must be one of: merge, parent, keep, reject.',
            'canonical_id.exists' => 'The specified canonical nutrient does not exist.',
        ];
    }
}
