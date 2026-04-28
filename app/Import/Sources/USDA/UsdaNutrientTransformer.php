<?php

namespace App\Import\Sources\USDA;

use App\Import\Records\NutrientRecord;

class UsdaNutrientTransformer {

    public function __construct(private readonly array $unitMap) {}

    public function transform(array $raw): NutrientRecord
    {
        return new NutrientRecord(
            externalId:      $raw['number'],
            name:            $raw['name'],
            description:     null,
            canonicalUnitId: $this->resolveUnit($raw['unitName']),
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