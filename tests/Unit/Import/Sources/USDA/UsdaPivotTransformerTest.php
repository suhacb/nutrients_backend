<?php

namespace Tests\Unit\Import\Sources\USDA;

use App\Import\Records\IngredientNutrientRecord;
use App\Import\Sources\USDA\UsdaPivotTransformer;
use PHPUnit\Framework\TestCase;

class UsdaPivotTransformerTest extends TestCase
{
    private array $unitMap = [
        'g'  => 1,
        'mg' => 2,
        'µg' => 3,
    ];

    private function makeTransformer(): UsdaPivotTransformer
    {
        return new UsdaPivotTransformer($this->unitMap);
    }

    private function rawFoodNutrient(): array
    {
        return [
            'type'     => 'FoodNutrient',
            'id'       => 2219709,
            'nutrient' => [
                'id'       => 1127,
                'number'   => '343',
                'name'     => 'Tocopherol, delta',
                'rank'     => 8200,
                'unitName' => 'mg',
            ],
            'amount'   => 1.3,
        ];
    }

    public function test_returns_ingredient_nutrient_record(): void
    {
        $record = $this->makeTransformer()->transform($this->rawFoodNutrient(), '321358');

        $this->assertInstanceOf(IngredientNutrientRecord::class, $record);
    }

    public function test_sets_ingredient_external_id_from_parameter(): void
    {
        $record = $this->makeTransformer()->transform($this->rawFoodNutrient(), '321358');

        $this->assertSame('321358', $record->ingredientExternalId);
    }

    public function test_maps_nutrient_number_to_nutrient_external_id(): void
    {
        $record = $this->makeTransformer()->transform($this->rawFoodNutrient(), '321358');

        $this->assertSame('343', $record->nutrientExternalId);
    }

    public function test_maps_amount(): void
    {
        $record = $this->makeTransformer()->transform($this->rawFoodNutrient(), '321358');

        $this->assertSame(1.3, $record->amount);
    }

    public function test_resolves_unit_name_to_amount_unit_id(): void
    {
        $record = $this->makeTransformer()->transform($this->rawFoodNutrient(), '321358');

        $this->assertSame(2, $record->amountUnitId);
    }

    public function test_throws_when_unit_name_not_in_map(): void
    {
        $raw = $this->rawFoodNutrient();
        $raw['nutrient']['unitName'] = 'kcal';

        $this->expectException(\RuntimeException::class);

        $this->makeTransformer()->transform($raw, '321358');
    }
}
