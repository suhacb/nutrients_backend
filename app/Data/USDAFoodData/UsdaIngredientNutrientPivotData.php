<?php
namespace App\Data\USDAFoodData;

use Exception;
use App\Models\Unit;
use App\Data\DataTransferObject;
use App\Models\IngredientNutrientPivot;
use RuntimeException;

class UsdaIngredientNutrientPivotData extends DataTransferObject
{
    /**
     * Converts USDA category JSON into internal array
     */
    public function toStage(array $context = []): array
    {
        $ingredient_id = $context['ingredient_id'] ?? null;
        $match = $context['match'] ?? null;
        $sourceNutrient = $context['sourceNutrient'] ?? [];

        // Validate presence of required data
        if (empty($ingredient_id)) {
            throw new RuntimeException('Missing ingredient_id in context.');
        }

        if (empty($match) || !isset($match->id)) {
            throw new RuntimeException('Invalid or missing match object in context.');
        }

        if (empty($sourceNutrient)) {
            throw new RuntimeException('Empty sourceNutrient provided to UsdaIngredientNutrientPivotData DTO.');
        }

        // Extra check for missing or invalid amount field
        if (!array_key_exists('amount', $sourceNutrient)) {
            throw new RuntimeException('Missing amount field in sourceNutrient data.');
        }

        return [
            'ingredient_id' => $ingredient_id,
            'nutrient_id'   => $match->id,
            'amount'        => (float) $sourceNutrient['amount'],
            'unit'          => $sourceNutrient['nutrient']['unitName'] ?? null,
        ];
    }
    
    public function toModel(): array
    {
        return [];

        // return [
        //     'amount' => $this->get('amount'),
        //     'amount_unit_id' => $this->getUnitId($this->get('nutrient.unitName')),
        //     'portion_amount' => null,
        //     'portion_amount_unit_id' => null
        // ];
    }

    protected function getUnitId(string $abbreviation): int
    {
        static $unitCache = [];

        if (isset($unitCache[$abbreviation])) {
            return $unitCache[$abbreviation];
        }

        $unit = Unit::where('abbreviation', $abbreviation)->first();

        if (!$unit) {
            logger()->error("Unit {$abbreviation} not found in database!");
            throw new Exception("Unit {$abbreviation} not found!");
        }

        return $unitCache[$abbreviation] = $unit->id;
    }
}