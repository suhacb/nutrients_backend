<?php

namespace Tests\Unit\Import\Sources\USDA;

use App\Import\Records\IngredientRecord;
use App\Import\Sources\USDA\UsdaIngredientTransformer;
use PHPUnit\Framework\TestCase;

class UsdaIngredientTransformerTest extends TestCase
{
    private function makeTransformer(): UsdaIngredientTransformer
    {
        return new UsdaIngredientTransformer();
    }

    private function rawFoundationFood(): array
    {
        return [
            'fdcId'        => 321358,
            'description'  => 'Hummus, commercial',
            'dataType'     => 'Foundation',
            'foodCategory' => ['description' => 'Legumes and Legume Products'],
        ];
    }

    private function rawBrandedFood(): array
    {
        return [
            'fdcId'               => 1106281,
            'description'         => 'GRANOLA, CINNAMON, RAISIN',
            'dataType'            => 'Branded',
            'foodCategory'        => null,
            'brandedFoodCategory' => 'Cereal',
        ];
    }

    public function test_returns_ingredient_record(): void
    {
        $record = $this->makeTransformer()->transform($this->rawFoundationFood());

        $this->assertInstanceOf(IngredientRecord::class, $record);
    }

    public function test_maps_fdc_id_to_external_id_as_string(): void
    {
        $record = $this->makeTransformer()->transform($this->rawFoundationFood());

        $this->assertSame('321358', $record->externalId);
    }

    public function test_maps_description_to_name(): void
    {
        $record = $this->makeTransformer()->transform($this->rawFoundationFood());

        $this->assertSame('Hummus, commercial', $record->name);
    }

    public function test_maps_data_type_to_class(): void
    {
        $record = $this->makeTransformer()->transform($this->rawFoundationFood());

        $this->assertSame('Foundation', $record->class);
    }

    public function test_maps_branded_data_type_to_class(): void
    {
        $record = $this->makeTransformer()->transform($this->rawBrandedFood());

        $this->assertSame('Branded', $record->class);
    }

    public function test_description_is_always_null(): void
    {
        $record = $this->makeTransformer()->transform($this->rawFoundationFood());

        $this->assertNull($record->description);
    }

    public function test_default_amount_is_always_null(): void
    {
        $record = $this->makeTransformer()->transform($this->rawFoundationFood());

        $this->assertNull($record->defaultAmount);
    }

    public function test_default_amount_unit_id_is_always_null(): void
    {
        $record = $this->makeTransformer()->transform($this->rawFoundationFood());

        $this->assertNull($record->defaultAmountUnitId);
    }
}
