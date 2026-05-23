<?php

namespace App\Services\Conversion;

use App\Models\Unit;

readonly class ConversionResult
{
    public function __construct(
        public float $value,
        public Unit  $unit,
    ) {}
}
