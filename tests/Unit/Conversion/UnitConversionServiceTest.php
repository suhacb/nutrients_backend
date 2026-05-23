<?php

namespace Tests\Unit\Conversion;

use App\Exceptions\IncompatibleUnitsException;
use App\Exceptions\MissingIUFactorException;
use App\Exceptions\UnsupportedUnitException;
use App\Models\Nutrient;
use App\Models\Unit;
use App\Services\Conversion\ConversionResult;
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

        $this->assertEqualsWithDelta(1.0, $result, 0.0001);
    }

    public function test_converts_grams_to_ounces(): void
    {
        $gram  = $this->unit('mass', 1.0);
        $ounce = $this->unit('mass', 28.34952);

        $result = $this->service->convert(100, $gram, $ounce);

        $this->assertEqualsWithDelta(3.5274, $result, 0.0001);
    }

    public function test_converts_kilograms_to_milligrams(): void
    {
        $kilogram  = $this->unit('mass', 1000.0);
        $milligram = $this->unit('mass', 0.001);

        $result = $this->service->convert(1, $kilogram, $milligram);

        $this->assertEqualsWithDelta(1_000_000.0, $result, 0.0001);
    }

    public function test_converts_ounces_to_pounds(): void
    {
        $ounce = $this->unit('mass', 28.34952);
        $pound = $this->unit('mass', 453.59237);

        $result = $this->service->convert(16, $ounce, $pound);

        $this->assertEqualsWithDelta(1.0, $result, 0.0001);
    }

    public function test_converts_micrograms_to_milligrams(): void
    {
        $microgram = $this->unit('mass', 0.000001);
        $milligram = $this->unit('mass', 0.001);

        $result = $this->service->convert(1000, $microgram, $milligram);

        $this->assertEqualsWithDelta(1.0, $result, 0.0001);
    }

    public function test_converts_liters_to_milliliters(): void
    {
        $liter      = $this->unit('volume', 1000.0);
        $milliliter = $this->unit('volume', 1.0);

        $result = $this->service->convert(1, $liter, $milliliter);

        $this->assertEqualsWithDelta(1000.0, $result, 0.0001);
    }

    public function test_converts_tablespoons_to_teaspoons(): void
    {
        $tablespoon = $this->unit('volume', 14.78677);
        $teaspoon   = $this->unit('volume', 4.92892);

        $result = $this->service->convert(1, $tablespoon, $teaspoon);

        $this->assertEqualsWithDelta(3.0, $result, 0.0001);
    }

    public function test_converts_kcal_to_kj(): void
    {
        $kcal = $this->unit('energy', 1.0);
        $kj   = $this->unit('energy', 0.23901);

        $result = $this->service->convert(1, $kcal, $kj);

        $this->assertEqualsWithDelta(4.184, $result, 0.0001);
    }

    public function test_converts_kj_to_kcal(): void
    {
        $kj   = $this->unit('energy', 0.23901);
        $kcal = $this->unit('energy', 1.0);

        $result = $this->service->convert(4.184, $kj, $kcal);

        $this->assertEqualsWithDelta(1.0, $result, 0.0001);
    }

    public function test_converts_iu_to_micrograms_for_vitamin_d(): void
    {
        $microgram = $this->unit('mass', 0.000001);

        $nutrient = new Nutrient(['iu_to_canonical_factor' => 0.025]);
        $nutrient->setRelation('canonicalUnit', $microgram);

        $result = $this->service->convertFromIU(400, $nutrient);

        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertEqualsWithDelta(10.0, $result->value, 0.0001);
        $this->assertSame($microgram, $result->unit);
    }

    public function test_converts_iu_to_micrograms_for_vitamin_a(): void
    {
        $microgram = $this->unit('mass', 0.000001);

        $nutrient = new Nutrient(['iu_to_canonical_factor' => 0.3]);
        $nutrient->setRelation('canonicalUnit', $microgram);

        $result = $this->service->convertFromIU(1000, $nutrient);

        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertEqualsWithDelta(300.0, $result->value, 0.0001);
        $this->assertSame($microgram, $result->unit);
    }

    public function test_throws_incompatible_units_exception_for_mass_to_volume(): void
    {
        $this->expectException(IncompatibleUnitsException::class);

        $gram       = $this->unit('mass', 1.0);
        $milliliter = $this->unit('volume', 1.0);

        $this->service->convert(100, $gram, $milliliter);
    }

    public function test_throws_unsupported_unit_exception_when_to_base_factor_is_null(): void
    {
        $this->expectException(UnsupportedUnitException::class);

        $gram = $this->unit('mass', 1.0);
        $iu   = new Unit(['type' => 'other', 'to_base_factor' => null]);

        $this->service->convert(100, $gram, $iu);
    }

    public function test_throws_missing_iu_factor_when_nutrient_has_no_factor(): void
    {
        $this->expectException(MissingIUFactorException::class);

        $nutrient = new Nutrient(['iu_to_canonical_factor' => null]);
        $nutrient->setRelation('canonicalUnit', $this->unit('mass', 0.000001));

        $this->service->convertFromIU(400, $nutrient);
    }

    public function test_throws_missing_iu_factor_when_nutrient_has_no_canonical_unit(): void
    {
        $this->expectException(MissingIUFactorException::class);

        $nutrient = new Nutrient(['iu_to_canonical_factor' => 0.025]);
        $nutrient->setRelation('canonicalUnit', null);

        $this->service->convertFromIU(400, $nutrient);
    }

    public function test_can_convert_returns_true_for_same_type(): void
    {
        $gram      = $this->unit('mass', 1.0);
        $kilogram  = $this->unit('mass', 1000.0);

        $this->assertTrue($this->service->canConvert($gram, $kilogram));
    }

    public function test_can_convert_returns_false_for_different_types(): void
    {
        $gram       = $this->unit('mass', 1.0);
        $milliliter = $this->unit('volume', 1.0);

        $this->assertFalse($this->service->canConvert($gram, $milliliter));
    }

    public function test_can_convert_returns_false_when_factor_missing(): void
    {
        $gram = $this->unit('mass', 1.0);
        $iu   = new Unit(['type' => 'other', 'to_base_factor' => null]);

        $this->assertFalse($this->service->canConvert($gram, $iu));
    }
}
