<?php

namespace Tests\Unit\Import\Sources\USDA;

use App\Import\Records\NutritionFactRecord;
use App\Import\Sources\USDA\UsdaNutritionFactTransformer;
use PHPUnit\Framework\TestCase;

class UsdaNutritionFactTransformerTest extends TestCase
{
    private array $unitMap = [
        'g'    => 1,
        'mg'   => 2,
        'kcal' => 3,
    ];

    private function makeTransformer(): UsdaNutritionFactTransformer
    {
        return new UsdaNutritionFactTransformer($this->unitMap);
    }

    private function rawLabelNutrients(): array
    {
        return [
            'protein'  => ['value' => 3.0],
            'fat'      => ['value' => 7.0],
            'sodium'   => ['value' => 45.1],
            'calories' => ['value' => 140.0],
        ];
    }

    public function test_returns_array_of_nutrition_fact_records(): void
    {
        $records = $this->makeTransformer()->transform($this->rawLabelNutrients(), '1106281');

        $this->assertIsArray($records);
        $this->assertContainsOnlyInstancesOf(NutritionFactRecord::class, $records);
    }

    public function test_returns_one_record_per_label_nutrient(): void
    {
        $records = $this->makeTransformer()->transform($this->rawLabelNutrients(), '1106281');

        $this->assertCount(4, $records);
    }

    public function test_sets_ingredient_external_id_on_each_record(): void
    {
        $records = $this->makeTransformer()->transform($this->rawLabelNutrients(), '1106281');

        foreach ($records as $record) {
            $this->assertSame('1106281', $record->ingredientExternalId);
        }
    }

    public function test_maps_key_to_name(): void
    {
        $records = $this->makeTransformer()->transform(['protein' => ['value' => 3.0]], '1106281');

        $this->assertSame('protein', $records[0]->name);
    }

    public function test_maps_value_to_amount(): void
    {
        $records = $this->makeTransformer()->transform(['protein' => ['value' => 3.0]], '1106281');

        $this->assertSame(3.0, $records[0]->amount);
    }

    public function test_sets_category_to_label_nutrients(): void
    {
        $records = $this->makeTransformer()->transform(['protein' => ['value' => 3.0]], '1106281');

        $this->assertSame('Label Nutrients', $records[0]->category);
    }

    public function test_resolves_gram_unit_for_protein(): void
    {
        $records = $this->makeTransformer()->transform(['protein' => ['value' => 3.0]], '1106281');

        $this->assertSame(1, $records[0]->amountUnitId);
    }

    public function test_resolves_milligram_unit_for_sodium(): void
    {
        $records = $this->makeTransformer()->transform(['sodium' => ['value' => 45.1]], '1106281');

        $this->assertSame(2, $records[0]->amountUnitId);
    }

    public function test_resolves_kcal_unit_for_calories(): void
    {
        $records = $this->makeTransformer()->transform(['calories' => ['value' => 140.0]], '1106281');

        $this->assertSame(3, $records[0]->amountUnitId);
    }

    public function test_skips_unknown_label_nutrient_keys(): void
    {
        $records = $this->makeTransformer()->transform([
            'protein' => ['value' => 3.0],
            'unknownNutrient' => ['value' => 1.0],
        ], '1106281');

        $this->assertCount(1, $records);
        $this->assertSame('protein', $records[0]->name);
    }

    public function test_returns_empty_array_for_empty_label_nutrients(): void
    {
        $records = $this->makeTransformer()->transform([], '1106281');

        $this->assertSame([], $records);
    }
}
