<?php

namespace App\Services\Conversion;

use App\Models\Nutrient;
use App\Models\Unit;

interface UnitConversionServiceContract
{
    public function convert(float $value, Unit $from, Unit $to): float;

    public function convertFromIU(float $value, Nutrient $nutrient): ConversionResult;

    public function canConvert(Unit $from, Unit $to): bool;
}
