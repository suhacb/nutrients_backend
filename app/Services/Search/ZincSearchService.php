<?php

namespace App\Services\Search;

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
        $response = Http::withBasicAuth($this->username, $this->password)
            ->post("{$this->baseUri}/api/{$index}/_doc", $document);

        return $response->successful();
    }

    public function update(string $index, string|int $id, array $document): bool
    {
        $response = Http::withBasicAuth($this->username, $this->password)
            ->put("{$this->baseUri}/api/{$index}/_doc/{$id}", $document);

        return $response->successful();
    }

    public function delete(string $index, string|int $id): bool
    {
        $response = Http::withBasicAuth($this->username, $this->password)
            ->delete("{$this->baseUri}/api/{$index}/_doc/{$id}");

        return $response->successful();
    }

    public function search(string $index, array $query, int $limit = 10, int $offset = 0): array
    {
        $response = Http::withBasicAuth($this->username, $this->password)
            ->post("{$this->baseUri}/api/{$index}/_search", $query);

        return $response->json() ?? [];
    }
}