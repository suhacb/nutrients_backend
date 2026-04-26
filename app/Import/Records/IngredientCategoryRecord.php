<?php

namespace App\Import\Records;

class IngredientCategoryRecord {
    public function __construct(
        public readonly string $name,
    ) {}
}