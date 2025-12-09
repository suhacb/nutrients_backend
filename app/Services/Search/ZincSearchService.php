<?php

namespace App\Services\Search;

use Exception;
use Illuminate\Support\Facades\Http;

class ZincSearchService implements SearchServiceContract
{
    protected string $baseUri;
    protected string $username;
    protected string $password;

    public function __construct(array $config)
    {
        $this->baseUri = $config['base_uri'];
        $this->username = $config['username'];
        $this->password = $config['password'];
    }

    protected function auth()
    {
        return [
            'auth' => [$this->username, $this->password]
        ];
    }

    public function insert(string $index, array $document): bool
    {
        $response = Http::withBasicAuth($this->username, $this->password)->post("{$this->baseUri}/api/{$index}/_doc", $document);
        
        if (!$response->successful()) {
            throw new \Exception("Search service unavailable");
        }

        return $response->successful();
    }

    public function update(string $index, string|int $id, array $document): bool
    {
        $response = Http::withBasicAuth($this->username, $this->password)->put("{$this->baseUri}/api/{$index}/_doc/{$id}", $document);
        
        if (!$response->successful()) {
            throw new \Exception("Search service unavailable");
        }

        return $response->successful();
    }

    public function delete(string $index, string|int $id): bool
    {
        $response = Http::withBasicAuth($this->username, $this->password)->delete("{$this->baseUri}/api/{$index}/_doc/{$id}");
        
        if (!$response->successful()) {
            throw new \Exception("Search service unavailable");
        }

        return $response->successful();
    }

    public function search(string $index, string $query, int $limit = 10, int $page = 1): SearchServiceResponse
    {
        $offset = ($page - 1) * $limit;

        if (empty(trim($query))) {
            // Avoid sending empty queries
            return new SearchServiceResponse(
                query: $query,
                index: $index,
                total: 0,
                perPage: $limit,
                results: []
            );
        }

        $payload = [
            'search_type' => 'match',
            'query' => [
                'term' => $query,
                'fields' => ["name^3", "description"],
            ],
            'from' => $offset,
            'size' => $limit,
        ];

        $response = Http::withBasicAuth($this->username, $this->password)
            ->post("{$this->baseUri}/api/{$index}/_search", $payload);

        if (!$response->successful()) {
            throw new Exception("Search service unavailable");
        }

        $data = $response->json();

        $total = $data['hits']['total'] ?? 0;
        $hits = $data['hits']['hits'] ?? [];

        $results = array_map(fn($hit) => [
            'id' => $hit['_source']['id'] ?? null,
            'name' => $hit['_source']['name'] ?? null,
            'description' => $hit['_source']['description'] ?? null,
            // 'source' => $hit['_source'] ?? null,
            'score' => $hit['_score'] ?? ($hit['_source']['score'] ?? null),
        ], $hits);

        return new SearchServiceResponse(
            query: $query,
            index: $index,
            total: is_array($total) ? ($total['value'] ?? 0) : (int)$total,
            perPage: $limit,
            results: $results
        );
    }
}