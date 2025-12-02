<?php

namespace App\Http\Requests;

use App\Http\Requests\DynamicRequest;

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
        return [];
    }

    protected function rulesForUpdate(): array
    {
        return [];
    }

    protected function messagesForStore(): array
    {
        return [];
    }

    protected function messagesForUpdate(): array
    {
        return [];
    }
}
