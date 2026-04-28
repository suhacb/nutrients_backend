<?php

namespace App\Import\Records;

class IngredientNutrientRecord {
    public function __construct(
        public readonly string $ingredientExternalId,                                                                         
        public readonly string $nutrientExternalId,  
        public readonly float  $amount,                                                                                       
        public readonly int    $amountUnitId,  
    ) {}
}