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
        $lastPage = (int) ceil($this->total / $this->perPage);
        $currentPage = max(1, (int) request()->get('page', 1));

        return [
            'query' => $this->query,
            'index' => $this->index,
            'total' => (int) $this->total,
            'per_page' => (int) $this->perPage,
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'from' => ($this->total > 0) ? (($currentPage - 1) * $this->perPage + 1) : null,
            'to' => ($this->total > 0)
                ? min($currentPage * $this->perPage, $this->total)
                : null,
            'results' => $this->results,
        ];
    }
}