<?php

namespace Tests\Unit\Import\Records;

use App\Import\Records\IngredientRecord;
use PHPUnit\Framework\TestCase;

class IngredientRecordTest extends TestCase
{
    public function test_constructor_sets_all_properties(): void
    {
        $record = new IngredientRecord(
            externalId:          '171705',
            name:                'Whole Milk',
            description:         'Full-fat dairy milk',
            class:               'Dairy',
            defaultAmount:       100.0,
            defaultAmountUnitId: 3,
        );

        $this->assertSame('171705', $record->externalId);
        $this->assertSame('Whole Milk', $record->name);
        $this->assertSame('Full-fat dairy milk', $record->description);
        $this->assertSame('Dairy', $record->class);
        $this->assertSame(100.0, $record->defaultAmount);
        $this->assertSame(3, $record->defaultAmountUnitId);
    }

    public function test_nullable_properties_accept_null(): void
    {
        $record = new IngredientRecord(
            externalId:          '171705',
            name:                'Whole Milk',
            description:         null,
            class:               null,
            defaultAmount:       null,
            defaultAmountUnitId: null,
        );

        $this->assertNull($record->description);
        $this->assertNull($record->class);
        $this->assertNull($record->defaultAmount);
        $this->assertNull($record->defaultAmountUnitId);
    }

    public function test_all_properties_are_readonly(): void
    {
        $reflection = new \ReflectionClass(IngredientRecord::class);

        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} should be readonly"
            );
        }
    }
}
