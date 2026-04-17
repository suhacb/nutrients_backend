<?php

namespace Tests\Unit\Units;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;

// 31-canonical-model

/**
 * Tests that the units table migration creates the correct schema,
 * including the canonical columns added in the 31-canonical-model feature.
 *
 * Verifies column presence, types, nullability, foreign key constraints,
 * unique constraints, and that the migration rolls back cleanly.
 */
class UnitsTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected $expectedColumns = [
        'id' => ['type' => 'bigint', 'nullable' => false],
        'name' => ['type' => 'varchar', 'nullable' => false],
        'abbreviation' => ['type' => 'varchar', 'nullable' => false],
        'type' => ['type' => 'enum', 'nullable' => true],
        'base_unit_id' => ['type' => 'bigint', 'nullable' => true],
        'to_base_factor' => ['type' => 'decimal', 'nullable' => true],
        'created_at' => ['type' => 'timestamp', 'nullable' => true],
        'updated_at' => ['type' => 'timestamp', 'nullable' => true],
    ];

    /**
     * Asserts that every expected column exists in the units table with the correct type and nullability.
     */
    public function test_units_table_has_expected_columns(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM units");

        $columns = [];
        foreach ($columnsInfo as $column) {
            $columns[$column->Field] = [
                'type' => $this->normalizeType($column->Type),
                'nullable' => $column->Null === 'YES',
            ];
        }

        foreach ($this->expectedColumns as $column => $details) {
            $this->assertArrayHasKey($column, $columns, "Column {$column} does not exist");

            $this->assertEquals(
                $details['type'],
                $columns[$column]['type'],
                "Column {$column} type mismatch (expected {$details['type']}, got {$columns[$column]['type']})"
            );

            $this->assertEquals(
                $details['nullable'],
                $columns[$column]['nullable'],
                "Column {$column} nullable mismatch (expected " . ($details['nullable'] ? 'YES' : 'NO') . ")"
            );
        }
    }

    /**
     * Asserts that the `type` column is a nullable enum containing the expected measurement categories
     * (mass, energy, volume, other).
     */
    public function test_units_table_has_type_column(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM units");
        $typeColumn = collect($columnsInfo)->firstWhere('Field', 'type');

        $this->assertNotNull($typeColumn, "Column 'type' does not exist");
        $this->assertSame('YES', $typeColumn->Null, "Column 'type' should be nullable");
        $this->assertStringContainsString('enum', strtolower($typeColumn->Type));

        foreach (['mass', 'energy', 'volume', 'other'] as $value) {
            $this->assertStringContainsString($value, $typeColumn->Type);
        }
    }

    /**
     * Asserts that `base_unit_id` is a nullable bigint with a self-referencing foreign key
     * that sets the column to NULL when the referenced unit is deleted.
     */
    public function test_units_table_has_base_unit_id_column(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM units");
        $column = collect($columnsInfo)->firstWhere('Field', 'base_unit_id');

        $this->assertNotNull($column, "Column 'base_unit_id' does not exist");
        $this->assertSame('YES', $column->Null, "Column 'base_unit_id' should be nullable");
        $this->assertStringStartsWith('bigint', strtolower($column->Type));

        $fks = DB::select("
            SELECT kcu.CONSTRAINT_NAME, rc.DELETE_RULE
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                AND rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
            WHERE kcu.TABLE_SCHEMA = DATABASE()
            AND kcu.TABLE_NAME = 'units'
            AND kcu.COLUMN_NAME = 'base_unit_id'
            AND kcu.REFERENCED_TABLE_NAME = 'units'
        ");

        $this->assertNotEmpty($fks, "Foreign key on 'base_unit_id' referencing 'units.id' should exist");
        $this->assertEquals('SET NULL', $fks[0]->DELETE_RULE, "Foreign key should cascade with SET NULL on delete");
    }

    /**
     * Asserts that `to_base_factor` is a nullable decimal column used to convert a unit to its base unit.
     */
    public function test_units_table_has_to_base_factor_column(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM units");
        $column = collect($columnsInfo)->firstWhere('Field', 'to_base_factor');

        $this->assertNotNull($column, "Column 'to_base_factor' does not exist");
        $this->assertSame('YES', $column->Null, "Column 'to_base_factor' should be nullable");
        $this->assertStringStartsWith('decimal', strtolower($column->Type));
    }

    /**
     * Asserts that calling `down()` on the canonical columns migration drops `base_unit_id` and
     * `to_base_factor`, and that `up()` re-creates them successfully.
     */
    public function test_migration_rolls_back_cleanly(): void
    {
        $this->assertTrue(Schema::hasColumn('units', 'base_unit_id'));
        $this->assertTrue(Schema::hasColumn('units', 'to_base_factor'));

        $migration = include database_path('migrations/2026_04_13_000001_add_canonical_columns_to_units_table.php');
        $migration->down();

        $this->assertFalse(Schema::hasColumn('units', 'base_unit_id'));
        $this->assertFalse(Schema::hasColumn('units', 'to_base_factor'));

        $migration->up();
    }

    /**
     * Asserts that a unit row can be inserted and retrieved with all expected field values intact.
     */
    public function test_can_insert_unit(): void
    {
        $unitId = DB::table('units')->insertGetId([
            'name' => 'Gram',
            'abbreviation' => 'g',
            'type' => 'mass',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $unit = DB::table('units')->find($unitId);
        $this->assertNotNull($unit);
        $this->assertEquals('Gram', $unit->name);
        $this->assertEquals('g', $unit->abbreviation);
        $this->assertEquals('mass', $unit->type);
    }

    /**
     * Asserts that a unit can be inserted without a `type` value and that the column stores NULL.
     */
    public function test_allows_nullable_type(): void
    {
        $unitId = DB::table('units')->insertGetId([
            'name' => 'Piece',
            'abbreviation' => 'pc',
            'type' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $unit = DB::table('units')->find($unitId);
        $this->assertNotNull($unit);
        $this->assertNull($unit->type);
    }

    /**
     * Asserts that duplicate (name, abbreviation, type) combinations are rejected by the database,
     * enforcing the unique constraint on the units table.
     */
    public function test_unique_constraints_on_name_and_abbreviation(): void
    {
        // Insert initial unit
        DB::table('units')->insert([
            'name' => 'Ounce',
            'abbreviation' => 'oz',
            'type' => 'mass',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempt to insert duplicate name + type → should fail
        $this->expectException(QueryException::class);
        DB::table('units')->insert([
            'name' => 'Ounce',
            'abbreviation' => 'oz',
            'type' => 'mass',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempt to insert duplicate abbreviation + type → should fail
        DB::table('units')->insert([
            'name' => 'Ounce2',
            'abbreviation' => 'oz',
            'type' => 'mass',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Strips the size specifier from a MySQL column type string (e.g. `varchar(255)` → `varchar`).
     */
    protected function normalizeType(string $type): string
    {
        $type = strtolower($type);
        $matches = [];
        if (preg_match('/^([a-z]+)/', $type, $matches)) {
            return $matches[1];
        }
        return $type;
    }
}
