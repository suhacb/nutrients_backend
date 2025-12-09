<?php
namespace App\Services\Search;

class SearchServiceResponse {
    public string $query;
    public string $index;
    public int $total;
    public int $perPage;
    /** @var array<int, array{id: mixed, name: ?string, description: ?string, score: ?float}> */
    public array $results;

    public function __construct(string $query, string $index, int $total, int $perPage, array $results)
    {
        $this->query = $query;
        $this->index = $index;
        $this->total = $total;
        $this->perPage = $perPage;
        $this->results = $results;
    }

    /**
     * Convert to array for controller JSON response.
     */
    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'index' => $this->index,
            'total' => $this->total,
            'per_page' => $this->perPage,
            'results' => $this->results,
        ];
    }
}