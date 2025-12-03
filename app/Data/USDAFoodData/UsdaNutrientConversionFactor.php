<?php
namespace App\Data\USDAFoodData;

use App\Data\DataTransferObject;
use App\Data\USDAFoodData\UsdaNutrientConversionFactorType;
use Exception;

class UsdaNutrientConversionFactor extends DataTransferObject
{
    private UsdaNutrientConversionFactorType $type;
    private ?float $proteinValue;
    private ?float $fatValue;
    private ?float $carbohydrateValue;
    private ?float $value;
    private ?float $nitrogenValue;

    public function __construct(array $data)
    {
        parent::__construct($data);
        
        if (!isset($data['type'])) {
            throw new Exception('Missing required type for NutrientConversionFactor');
        }

        // Convert type string to enum instance
        $this->type = UsdaNutrientConversionFactorType::from($data['type']);

        $this->proteinValue = isset($data['proteinValue']) ? floatval($data['proteinValue']) : null;
        $this->fatValue = isset($data['fatValue']) ? floatval($data['fatValue']) : null;
        $this->carbohydrateValue = isset($data['carbohydrateValue']) ? floatval($data['carbohydrateValue']) : null;
        $this->value = isset($data['value']) ? floatval($data['value']) : null;
        $this->nitrogenValue = isset($data['nitrogenValue']) ? floatval($data['nitrogenValue']) : null;
    }

    /**
     * Convert DTO to array (for import / export)
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->enum, // enum as string
            'proteinValue' => $this->proteinValue,
            'fatValue' => $this->fatValue,
            'carbohydrateValue' => $this->carbohydrateValue,
            'value' => $this->value,
            'nitrogenValue' => $this->nitrogenValue,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static($data);
    }
}
