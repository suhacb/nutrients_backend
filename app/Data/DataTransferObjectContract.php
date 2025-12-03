<?php
namespace App\Data;

use Illuminate\Database\Eloquent\Model;

interface DataTransferObjectContract
{
    /**
     * Factory method to create a DTO instance from raw array data.
     */
    public static function fromArray(array $data): static;

    /**
     * Returns the raw input data (as received or slightly normalized).
     */
    public function getRaw(): array;

    /**
     * Returns a normalized array that matches your internal Laravel data structure.
     */
    public function toArray(): array;

    /**
     * Builds (but does not persist) the corresponding Eloquent model instance.
     */
    public function toModel(): Model;

    /**
     * Optional: Validate the DTO's data.
     */
    public function validate(): void;

    /**
     * Optional: Check if the DTO’s data passes validation rules.
     */
    public function isValid(): bool;

    /**
     * Returns validation errors if any exist.
     */
    public function errors(): array;
}