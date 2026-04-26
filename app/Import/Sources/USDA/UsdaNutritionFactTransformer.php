<?php

namespace App\Import\Sources\USDA;

use App\Import\Records\NutritionFactRecord;

class UsdaNutritionFactTransformer {

    private const LABEL_NUTRIENT_UNITS = [
        'fat'           => 'g',
        'saturatedFat'  => 'g',
        'transFat'      => 'g',
        'carbohydrates' => 'g',
        'fiber'         => 'g',
        'sugars'        => 'g',
        'protein'       => 'g',
        'cholesterol'   => 'mg',
        'sodium'        => 'mg',
        'calcium'       => 'mg',
        'iron'          => 'mg',
        'calories'      => 'kcal',
    ];

    public function __construct(private readonly array $unitMap) {}

    public function transform(array $labelNutrients, string $ingredientExternalId): array
    {
        $records = [];

        foreach ($labelNutrients as $key => $entry) {
            if (!isset(self::LABEL_NUTRIENT_UNITS[$key])) {
                continue;
            }

            $abbreviation = self::LABEL_NUTRIENT_UNITS[$key];

            if (!array_key_exists($abbreviation, $this->unitMap)) {
                continue;
            }

            $records[] = new NutritionFactRecord(
                ingredientExternalId: $ingredientExternalId,
                category:             'Label Nutrients',
                name:                 $key,
                amount:               (float) $entry['value'],
                amountUnitId:         $this->unitMap[$abbreviation],
            );
        }

        return $records;
    }
}