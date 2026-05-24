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
            'decision' => ['required', 'string', 'in:merge,keep,reject'],
        ];
    }

    protected function messagesForResolve(): array
    {
        return [
            'decision.required' => 'A decision is required.',
            'decision.in'       => 'Decision must be one of: merge, keep, reject.',
        ];
    }
}
