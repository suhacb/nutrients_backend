<?php

namespace Tests\Unit\Import\Records;

use App\Import\Records\NutrientRecord;
use Tests\TestCase;

class NutrientRecordTest extends TestCase
{
    public function test_constructor_sets_all_properties(): void
    {
        $record = new NutrientRecord(
            externalId:      '1003',
            name:            'Protein',
            description:     'Total protein content',
            canonicalUnitId: 5,
        );

        $this->assertSame('1003', $record->externalId);
        $this->assertSame('Protein', $record->name);
        $this->assertSame('Total protein content', $record->description);
        $this->assertSame(5, $record->canonicalUnitId);
    }

    public function test_nullable_properties_accept_null(): void
    {
        $record = new NutrientRecord(
            externalId:      '1003',
            name:            'Protein',
            description:     null,
            canonicalUnitId: null,
        );

        $this->assertNull($record->description);
        $this->assertNull($record->canonicalUnitId);
    }

    public function test_all_properties_are_readonly(): void
    {
        $reflection = new \ReflectionClass(NutrientRecord::class);

        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} should be readonly"
            );
        }
    }
}
