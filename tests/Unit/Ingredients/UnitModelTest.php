<?php

namespace Tests\Unit\Ingredients;

use Tests\TestCase;
use App\Models\Unit;

class UnitModelTest extends TestCase
{
    public function test_it_has_correct_table_and_fillable_fields(): void
    {
        $unit = new Unit();

        $this->assertEquals('units', $unit->getTable());
        $this->assertEquals(['name', 'abbreviation', 'type'], $unit->getFillable());
    }
}
