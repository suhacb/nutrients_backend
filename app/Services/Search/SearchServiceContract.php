<?php
namespace App\Services\Search;

interface SearchServiceContract {
    public function insert(string $index, string|int $id, array $document): bool;

    public function update(string $index, string|int $id, array $document): bool;

    public function delete(string $index, string|int $id): bool;

    /** Bulk-insert documents in one request. $documents is keyed by document ID. */
    public function bulkInsert(string $index, array $documents): void;

    public function search(string $index, string $query, int $limit = 10, int $offset = 0): SearchServiceResponse;

    public function get(string $index, string|int $id): ?array;
}