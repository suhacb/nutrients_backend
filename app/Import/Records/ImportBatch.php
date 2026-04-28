<?php

namespace App\Import\Records;

use App\Import\Records\IngredientCategoryRecord;
use App\Import\Records\IngredientRecord;

class ImportBatch {
    public function __construct(
        public readonly IngredientRecord         $ingredient,
        public readonly IngredientCategoryRecord $category,
        public readonly array                    $nutrients,            // NutrientRecord[]
        public readonly array                    $ingredientNutrients,  // IngredientNutrientRecord[]
        public readonly array                    $nutritionFacts,       // NutritionFactRecord[]
    ) {}
}