<?php

namespace Tests\Unit\Units;

use Tests\TestCase;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;

// 31-canonical-model

/**
 * Tests the Unit Eloquent model configuration introduced in the 31-canonical-model feature:
 * table name, fillable fields, casts, and the self-referencing base/derived unit relationships.
 */
class UnitModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Asserts that the model targets the `units` table and declares exactly the expected fillable fields,
     * including the canonical columns `type`, `base_unit_id`, and `to_base_factor`.
     */
    public function test_it_has_correct_table_and_fillable_fields(): void
    {
        $unit = new Unit();

        $this->assertEquals('units', $unit->getTable());
        $this->assertEquals(['name', 'abbreviation', 'type', 'base_unit_id', 'to_base_factor'], $unit->getFillable());
    }

    /**
     * Asserts that the canonical attributes (`type`, `base_unit_id`, `to_base_factor`) are all present
     * in the model's fillable array.
     */
    public function test_fillable_includes_new_attributes(): void
    {
        $fillable = (new Unit())->getFillable();

        $this->assertContains('type', $fillable);
        $this->assertContains('base_unit_id', $fillable);
        $this->assertContains('to_base_factor', $fillable);
    }

    /**
     * Asserts that `to_base_factor` is cast to `decimal:10` so conversion arithmetic retains precision.
     */
    public function test_to_base_factor_cast_to_decimal(): void
    {
        $casts = (new Unit())->getCasts();

        $this->assertArrayHasKey('to_base_factor', $casts);
        $this->assertEquals('decimal:10', $casts['to_base_factor']);
    }

    /**
     * Asserts that the `baseUnit` BelongsTo relationship returns the correct parent Unit instance
     * when a derived unit references a base unit via `base_unit_id`.
     */
    public function test_base_unit_relationship_resolves(): void
    {
        $baseUnit = Unit::create([
            'name' => 'Gram',
            'abbreviation' => 'g',
            'type' => 'mass',
            'to_base_factor' => 1.0,
        ]);

        $derivedUnit = Unit::create([
            'name' => 'Kilogram',
            'abbreviation' => 'kg',
            'type' => 'mass',
            'base_unit_id' => $baseUnit->id,
            'to_base_factor' => 1000.0,
        ]);

        $this->assertInstanceOf(Unit::class, $derivedUnit->baseUnit);
        $this->assertEquals($baseUnit->id, $derivedUnit->baseUnit->id);
    }

    /**
     * Asserts that the `derivedUnits` HasMany relationship returns all child units
     * that point to a given base unit via `base_unit_id`.
     */
    public function test_derived_units_relationship_resolves(): void
    {
        $baseUnit = Unit::create([
            'name' => 'Gram',
            'abbreviation' => 'g',
            'type' => 'mass',
            'to_base_factor' => 1.0,
        ]);

        $derivedUnit = Unit::create([
            'name' => 'Kilogram',
            'abbreviation' => 'kg',
            'type' => 'mass',
            'base_unit_id' => $baseUnit->id,
            'to_base_factor' => 1000.0,
        ]);

        $this->assertCount(1, $baseUnit->derivedUnits);
        $this->assertEquals($derivedUnit->id, $baseUnit->derivedUnits->first()->id);
    }
}
