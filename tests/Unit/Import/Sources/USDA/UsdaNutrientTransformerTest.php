<?php

namespace Tests\Unit\Import\Sources\USDA;

use App\Import\Records\NutrientRecord;
use App\Import\Sources\USDA\UsdaNutrientTransformer;
use PHPUnit\Framework\TestCase;

class UsdaNutrientTransformerTest extends TestCase
{
    private array $unitMap = [
        'g'  => 1,
        'mg' => 2,
        'µg' => 3,
    ];

    private function makeTransformer(): UsdaNutrientTransformer
    {
        return new UsdaNutrientTransformer($this->unitMap);
    }

    private function rawNutrient(string $number, string $name, string $unitName): array
    {
        return [
            'id'       => 1003,
            'number'   => $number,
            'name'     => $name,
            'rank'     => 600,
            'unitName' => $unitName,
        ];
    }

    public function test_returns_nutrient_record(): void
    {
        $record = $this->makeTransformer()->transform(
            $this->rawNutrient('203', 'Protein', 'g')
        );

        $this->assertInstanceOf(NutrientRecord::class, $record);
    }

    public function test_maps_number_to_external_id(): void
    {
        $record = $this->makeTransformer()->transform(
            $this->rawNutrient('203', 'Protein', 'g')
        );

        $this->assertSame('203', $record->externalId);
    }

    public function test_maps_name(): void
    {
        $record = $this->makeTransformer()->transform(
            $this->rawNutrient('203', 'Protein', 'g')
        );

        $this->assertSame('Protein', $record->name);
    }

    public function test_description_is_always_null(): void
    {
        $record = $this->makeTransformer()->transform(
            $this->rawNutrient('203', 'Protein', 'g')
        );

        $this->assertNull($record->description);
    }

    public function test_resolves_unit_name_to_canonical_unit_id(): void
    {
        $record = $this->makeTransformer()->transform(
            $this->rawNutrient('334', 'Cryptoxanthin, beta', 'µg')
        );

        $this->assertSame(3, $record->canonicalUnitId);
    }

    public function test_throws_when_unit_name_not_in_map(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->makeTransformer()->transform(
            $this->rawNutrient('203', 'Protein', 'kcal')
        );
    }
}
