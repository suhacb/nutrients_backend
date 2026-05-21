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

    public function insert(string $index, string|int $id, array $document): bool
    {
        $response = Http::withBasicAuth($this->username, $this->password)->put("{$this->baseUri}/api/{$index}/_doc/{$id}", $document);
        
        if (!$response->successful()) {
            throw new Exception("Search service unavailable");
        }

        return $response->successful();
    }

    public function update(string $index, string|int $id, array $document): bool
    {
        $response = Http::withBasicAuth($this->username, $this->password)->put("{$this->baseUri}/api/{$index}/_doc/{$id}", $document);
        
        if (!$response->successful()) {
            throw new Exception("Search service unavailable");
        }

        return $response->successful();
    }

    public function bulkInsert(string $index, array $documents): void
    {
        if (empty($documents)) {
            return;
        }

        $lines = [];
        foreach ($documents as $id => $payload) {
            $lines[] = json_encode(['index' => ['_id' => (string) $id]]);
            $lines[] = json_encode($payload);
        }
        $ndjson = implode("\n", $lines) . "\n";

        $response = Http::withBasicAuth($this->username, $this->password)
            ->withBody($ndjson, 'application/x-ndjson')
            ->post("{$this->baseUri}/api/{$index}/_bulk");

        if (!$response->successful()) {
            throw new Exception("Zinc bulk insert into '{$index}' failed ({$response->status()}): " . $response->body());
        }
    }

    public function delete(string $index, string|int $id): bool
    {
        $response = Http::withBasicAuth($this->username, $this->password)->delete("{$this->baseUri}/api/{$index}/_doc/{$id}");

        if ($response->status() === 404) {
            return true;
        }

        if (!$response->successful()) {
            throw new Exception("Search service unavailable");
        }

        return true;
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
            'max_results' => $limit,
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
            'id'          => $hit['_source']['id'] ?? null,
            'name'        => $hit['_source']['name'] ?? null,
            'description' => $hit['_source']['description'] ?? null,
            'score'       => $hit['_score'] ?? ($hit['_source']['score'] ?? null),
        ], $hits);

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return new SearchServiceResponse(
            query: $query,
            index: $index,
            total: is_array($total) ? ($total['value'] ?? 0) : (int)$total,
            perPage: $limit,
            results: $results
        );
    }

    public function get(string $index, string|int $id): ?array
    {
        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->get("{$this->baseUri}/api/{$index}/_doc/{$id}");
        } catch (\Throwable $e) {
            logger()->warning("Zinc get failed for {$index}/{$id}: " . $e->getMessage());
            return null;
        }

        if ($response->status() === 404) {
            return null;
        }

        if (!$response->successful()) {
            logger()->warning("Zinc get returned {$response->status()} for {$index}/{$id}");
            return null;
        }

        return $response->json('_source');
    }
}