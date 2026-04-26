<?php

namespace App\Import\Records;

class IngredientRecord {
    public function __construct(
        public readonly string  $externalId,
        public readonly string  $name,                                                                                        
        public readonly ?string $description,                     
        public readonly ?string $class,      
        public readonly ?float  $defaultAmount,
        public readonly ?int    $defaultAmountUnitId,
    ) {}
}