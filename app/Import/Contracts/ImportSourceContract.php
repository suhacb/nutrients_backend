<?php

namespace App\Import\Contracts;

interface ImportSourceContract {
    /**
     * Return the Source model record that identifies this data source.
     * Must pre-exist in the database — the import fails fast if missing.
     */
    public function getSource(): \App\Models\Source;

    /**
     * Stream raw records from the given file path one at a time.
     * Each yielded value is an associative array representing one food item.
     */
    public function stream(string $file): \Generator;

    /**
     * Transform a single raw record into an ImportBatch containing all
     * source-agnostic record types for that item.
     */
    public function transform(array $raw): \App\Import\Records\ImportBatch;
}