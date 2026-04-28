<?php

namespace App\Import\Sources\USDA;

use App\Import\Records\IngredientRecord;

class UsdaIngredientTransformer {

    public function transform(array $raw): IngredientRecord
    {
        return new IngredientRecord(
            externalId:          strval($raw['fdcId']),
            name:                $raw['description'],
            description:         null,
            class:               $raw['dataType'] ?? null,
            defaultAmount:       null,
            defaultAmountUnitId: null,
        );
    }
}