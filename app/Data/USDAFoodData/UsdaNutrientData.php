<?php
namespace App\Data\USDAFoodData;

use Exception;
use App\Models\Unit;
use App\Models\Nutrient;
use App\Data\DataTransferObject;

class UsdaNutrientData extends DataTransferObject
{
    /**
     * Converts USDA nutrient JSON into internal nutrient
     */
    public function toStage(array $context = []): array
    {
        return [
            'external_id' => $this->raw['number'],
            'name' => $this->raw['name'],
        ];
    }

    public function toModel(): array
    {
        return [
            'source' => 'USDA FoodData Central',
            'external_id' => strval($this->get('external_id', null)),
            'name' => $this->get('name'),
            'description' => null,
        ];
    }

    /**
     * Creates a pivot-ready array for ingredient_nutrient table
     * Optionally resolves Unit model by name.
     */
    public function toPivotArray(): array
    {
        // $unit = Unit::where('name', $this->unitName)->orWhere('abbreviation', $this->unitName)->first();

        return [
            'amount' => $this->get('amount'),
            'amount_unit_id' => $this->getUnitId($this->get('nutrient.unitName')),
            'portion_amount' => null,
            'portion_amount_unit_id' => null,
        ];
    }

    protected function getUnitId(string $abbreviation): int
    {
        $unit = Unit::where('abbreviation', $abbreviation)->first();

        if (!$unit) {
            logger()->error("Unit {$abbreviation} not found in database!");
            throw new Exception("Unit {$abbreviation} not found!");
        }
        return $unit->id;
    }
}