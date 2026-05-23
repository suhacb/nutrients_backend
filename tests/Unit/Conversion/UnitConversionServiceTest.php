<?php

namespace Tests\Unit\Conversion;

use App\Models\Unit;
use App\Services\Conversion\UnitConversionService;
use Tests\TestCase;

class UnitConversionServiceTest extends TestCase
{
    private UnitConversionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UnitConversionService();
    }

    private function unit(string $type, float $toBaseFactor): Unit
    {
        return new Unit(['type' => $type, 'to_base_factor' => $toBaseFactor]);
    }

    public function test_converts_grams_to_kilograms(): void
    {
        $gram     = $this->unit('mass', 1.0);
        $kilogram = $this->unit('mass', 1000.0);

        $result = $this->service->convert(1000, $gram, $kilogram);

        $this->assertEqualsWithDelta(1.0, $result, 0.000001);
    }
}
