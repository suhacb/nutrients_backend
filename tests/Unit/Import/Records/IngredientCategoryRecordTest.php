<?php

namespace Tests\Unit\Import\Records;

use App\Import\Records\IngredientCategoryRecord;
use PHPUnit\Framework\TestCase;

class IngredientCategoryRecordTest extends TestCase
{
    public function test_constructor_sets_name(): void
    {
        $record = new IngredientCategoryRecord(name: 'Dairy and Egg Products');

        $this->assertSame('Dairy and Egg Products', $record->name);
    }

    public function test_all_properties_are_readonly(): void
    {
        $reflection = new \ReflectionClass(IngredientCategoryRecord::class);

        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} should be readonly"
            );
        }
    }
}
