<?php

namespace App\Services\Conversion;

use App\Exceptions\IncompatibleUnitsException;
use App\Exceptions\MissingIUFactorException;
use App\Exceptions\UnsupportedUnitException;
use App\Models\Nutrient;
use App\Models\Unit;

class UnitConversionService implements UnitConversionServiceContract
{
    public function convert(float $value, Unit $from, Unit $to): float
    {
        if ($from->to_base_factor === null || $to->to_base_factor === null) {
            throw new UnsupportedUnitException(
                "Unit '{$from->abbreviation}' or '{$to->abbreviation}' has no conversion factor."
            );
        }

        if ($from->type !== $to->type) {
            throw new IncompatibleUnitsException(
                "Cannot convert '{$from->type}' to '{$to->type}'."
            );
        }

        return round($value * $from->to_base_factor / $to->to_base_factor, 6);
    }

    public function convertFromIU(float $value, Nutrient $nutrient): ConversionResult
    {
        if ($nutrient->iu_to_canonical_factor === null) {
            throw new MissingIUFactorException(
                "Nutrient '{$nutrient->name}' has no IU conversion factor."
            );
        }

        if ($nutrient->canonicalUnit === null) {
            throw new MissingIUFactorException(
                "Nutrient '{$nutrient->name}' has no canonical unit."
            );
        }

        return new ConversionResult(
            value: round($value * $nutrient->iu_to_canonical_factor, 6),
            unit:  $nutrient->canonicalUnit,
        );
    }

    public function canConvert(Unit $from, Unit $to): bool
    {
        if ($from->to_base_factor === null || $to->to_base_factor === null) {
            return false;
        }

        return $from->type === $to->type;
    }
}
