<?php

namespace Tests\Unit\Import\Records;

use App\Import\Records\NutritionFactRecord;
use PHPUnit\Framework\TestCase;

class NutritionFactRecordTest extends TestCase
{
    public function test_constructor_sets_all_properties(): void
    {
        $record = new NutritionFactRecord(
            ingredientExternalId: '171705',
            category:             'Proximates',
            name:                 'Protein',
            amount:               3.15,
            amountUnitId:         2,
        );

        $this->assertSame('171705', $record->ingredientExternalId);
        $this->assertSame('Proximates', $record->category);
        $this->assertSame('Protein', $record->name);
        $this->assertSame(3.15, $record->amount);
        $this->assertSame(2, $record->amountUnitId);
    }

    public function test_all_properties_are_readonly(): void
    {
        $reflection = new \ReflectionClass(NutritionFactRecord::class);

        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} should be readonly"
            );
        }
    }
}
