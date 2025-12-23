<?php
namespace App\Data\USDAFoodData;

use Exception;
use App\Models\Unit;
use App\Models\Ingredient;
use Illuminate\Support\Str;
use App\Data\DataTransferObject;

class UsdaIngredientData extends DataTransferObject
{
    /**
     * Converts a single USDA ingredient source JSON into stage array structure
     */
    public function toStage(array $context = []): array
    {
        $finalDescription = Str::length($this->raw['description']) > 255 ? Str::limit($this->raw['description'], 250, '...') : $this->raw['description'];
        
        return [
            'foodClass' => $this->get('foodClass'),
            'description' => $finalDescription,
            'ndbNumber' => array_key_exists('ndbNumber', $this->raw) ? $this->raw['ndbNumber'] : null,
            'dataType' => $this->get('dataType'),
            'fdcId' => $this->get('fdcId'),
        ];
    }

    public function toModel(): array
    {
        return [
            'source' => 'USDA FoodData Central',
            'external_id' => $this->get('ndbNumber'),
            'name' => $this->get('description'),
            'description' => null,
            'class' => $this->get('foodClass'),
            'default_amount' => 100,
            'default_amount_unit_id' => $this->getUnitId('g'),
        ];
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