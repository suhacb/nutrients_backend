<?php

namespace Tests\Unit\Import\Records;

use App\Import\Records\IngredientNutrientRecord;
use PHPUnit\Framework\TestCase;

class IngredientNutrientRecordTest extends TestCase
{
    public function test_constructor_sets_all_properties(): void
    {
        $record = new IngredientNutrientRecord(
            ingredientExternalId: '171705',
            nutrientExternalId:   '1003',
            amount:               25.5,
            amountUnitId:         2,
        );

        $this->assertSame('171705', $record->ingredientExternalId);
        $this->assertSame('1003', $record->nutrientExternalId);
        $this->assertSame(25.5, $record->amount);
        $this->assertSame(2, $record->amountUnitId);
    }

    public function test_all_properties_are_readonly(): void
    {
        $reflection = new \ReflectionClass(IngredientNutrientRecord::class);

        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} should be readonly"
            );
        }
    }
}
