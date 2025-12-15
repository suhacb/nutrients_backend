<?php
namespace App\Services\Search;

interface SearchServiceContract {
    public function insert(string $index, array $document): bool;

    public function update(string $index, string|int $id, array $document): bool;

    public function delete(string $index, string|int $id): bool;

    public function search(string $index, string $query, int $limit = 10, int $offset = 0): SearchServiceResponse;
}