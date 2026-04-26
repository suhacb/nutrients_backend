<?php

namespace App\Import\Records;

use App\Import\Records\IngredientCategoryRecord;
use App\Import\Records\IngredientRecord;
use App\Import\Records\NutrientRecord;

class ImportBatch {
    public function __construct(
        public readonly NutrientRecord           $nutrient,
        public readonly IngredientRecord         $ingredient,                                                                 
        public readonly IngredientCategoryRecord $category,                                                                   
        public readonly array                    $ingredientNutrients,  // IngredientNutrientRecord[]
        public readonly array                    $nutritionFacts,       // NutritionFactRecord[]   
    ){}
}