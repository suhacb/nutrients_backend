<?php
namespace App\Data\USDAFoodData;

use Exception;
use App\Data\DataTransferObject;


class UsdaNutrientConversionFactorType
{
    public static UsdaNutrientConversionFactorType $CALORIE_CONVERSION_FACTOR;
    public static UsdaNutrientConversionFactorType $PROTEIN_CONVERSION_FACTOR;

    public static function init()
    {
        UsdaNutrientConversionFactorType::$CALORIE_CONVERSION_FACTOR = new UsdaNutrientConversionFactorType('.CalorieConversionFactor');
        UsdaNutrientConversionFactorType::$PROTEIN_CONVERSION_FACTOR = new UsdaNutrientConversionFactorType('.ProteinConversionFactor');
    }

    public string $enum;
    public function __construct(string $enum)
    {
        $this->enum = $enum;
    }

    /**
     * @param UsdaNutrientConversionFactorType
     * @return string
     * @throws Exception
     */
    public static function to(UsdaNutrientConversionFactorType $obj): string {
        switch ($obj->enum) {
            case UsdaNutrientConversionFactorType::$CALORIE_CONVERSION_FACTOR->enum: return '.CalorieConversionFactor';
            case UsdaNutrientConversionFactorType::$PROTEIN_CONVERSION_FACTOR->enum: return '.ProteinConversionFactor';
        }
        throw new Exception('the give value is not an enum-value.');
    }

    /**
     * @param mixed
     * @return UsdaNutrientConversionFactorType
     * @throws Exception
     */
    public static function from($obj): UsdaNutrientConversionFactorType
    {
        switch ($obj) {
            case '.CalorieConversionFactor': return UsdaNutrientConversionFactorType::$CALORIE_CONVERSION_FACTOR;
            case '.ProteinConversionFactor': return UsdaNutrientConversionFactorType::$PROTEIN_CONVERSION_FACTOR;
        }
        throw new Exception("Cannot deserialize NutrientConversionFactorType");
    }

    /**
     * @return UsdaNutrientConversionFactorType
     */
    public static function sample(): UsdaNutrientConversionFactorType
    {
        return UsdaNutrientConversionFactorType::$CALORIE_CONVERSION_FACTOR;
    }

}
//NutrientConversionFactorType::init();