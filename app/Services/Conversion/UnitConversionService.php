<?php

namespace App\Services\Conversion;

use App\Models\Unit;

class UnitConversionService
{
    public function convert(float $value, Unit $from, Unit $to): float
    {
        return round($value * $from->to_base_factor / $to->to_base_factor, 6);
    }
}
