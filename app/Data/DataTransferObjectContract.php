<?php
namespace App\Data;

use Illuminate\Database\Eloquent\Model;

interface DataTransferObjectContract
{
    /**
     * Get the singleton instance of the DTO.
     */
    public static function instance(): static;

    /**
     * Load raw data into the singleton for transformation.
     */
    public function load(array $data): static;

    /**
     * Returns the raw input data (as received or slightly normalized).
     */
    public function getRaw(): ?array;

    /**
     * Converts loaded data to internal normalized array structure.
     */
    public function toModel(): array;

    public function toStage(array $context = []): array;
}