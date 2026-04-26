<?php

namespace App\Import\Sources\USDA;

class UsdaPivotTransformer {

    public function __construct(private readonly array $unitMap) {}

    public function transform(array $raw, string $ingredientExternalId): \App\Import\Records\IngredientNutrientRecord
    {
        return new \App\Import\Records\IngredientNutrientRecord(
            ingredientExternalId: $ingredientExternalId,
            nutrientExternalId:   $raw['nutrient']['number'],
            amount:               (float) $raw['amount'],
            amountUnitId:         $this->resolveUnit($raw['nutrient']['unitName']),
        );
    }

    private function resolveUnit(string $abbreviation): int
    {
        if (!array_key_exists($abbreviation, $this->unitMap)) {
            throw new \RuntimeException("Unknown unit abbreviation: \"{$abbreviation}\"");
        }

        return $this->unitMap[$abbreviation];
    }
}