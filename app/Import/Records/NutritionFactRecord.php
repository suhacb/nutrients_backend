<?php

namespace App\Import\Records;

class NutritionFactRecord {
    public function __construct(
        public readonly string $ingredientExternalId,                                                                         
        public readonly string $category,                         
        public readonly string $name,                                                                                         
        public readonly float  $amount,                           
        public readonly int    $amountUnitId,
    ){}
}