<?php

namespace App\Http\Requests;

use App\Http\Requests\DynamicRequest;

class SearchRequest extends DynamicRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function rulesForSearch(): array
    {
        return [
            'query' => 'required|string|max:255',
            'index' => 'required|string|max:100',
            'page' => 'sometimes|integer|min:1',
        ];
    }

    protected function messagesForSearch(): array
    {
        return [
            'query.required' => 'Search query is required.',
            'index.required' => 'Search index must be specified.',
            'page.integer' => 'Page must be a positive integer.',
        ];
    }

    /**
     * Return a cache key for this search request.
     */
    public function cacheKey(): string
    {
        $userId = $this->user()->id ?? 0;
        return "search:{$userId}:{$this->index}:" . md5($this->input('query'));
    }

    /**
     * Return the current page (default 1)
     */
    public function page(): int
    {
        return $this->input('page', 1);
    }
}
