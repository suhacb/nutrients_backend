<?php

namespace Tests\Unit\Nutrients;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tests that the nutrients table migration creates the correct schema,
 * including canonical columns (parent_id, slug, canonical_unit_id,
 * iu_to_canonical_factor, is_label_standard, display_order) added alongside
 * the base nutrient columns.
 *
 * Verifies column presence, types, nullability, foreign key constraints,
 * unique indexes, and that the migration rolls back cleanly.
 */
class NutrientsMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected $expectedColumns = [
        'id'                     => ['type' => 'bigint',    'nullable' => false],
        'source'                 => ['type' => 'varchar',   'nullable' => false],
        'external_id'            => ['type' => 'varchar',   'nullable' => true],
        'name'                   => ['type' => 'varchar',   'nullable' => false],
        'description'            => ['type' => 'text',      'nullable' => true],
        'parent_id'              => ['type' => 'bigint',    'nullable' => true],
        'slug'                   => ['type' => 'varchar',   'nullable' => true],
        'canonical_unit_id'      => ['type' => 'bigint',    'nullable' => true],
        'iu_to_canonical_factor' => ['type' => 'decimal',   'nullable' => true],
        'is_label_standard'      => ['type' => 'tinyint',   'nullable' => false],
        'display_order'          => ['type' => 'int',       'nullable' => true],
        'created_at'             => ['type' => 'timestamp', 'nullable' => true],
        'updated_at'             => ['type' => 'timestamp', 'nullable' => true],
        'deleted_at'             => ['type' => 'timestamp', 'nullable' => true],
    ];

    /**
     * Asserts that every expected column exists in the nutrients table with the correct type and nullability.
     */
    public function test_nutrients_table_has_expected_columns(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM nutrients");

        $columns = [];
        foreach ($columnsInfo as $column) {
            $columns[$column->Field] = [
                'type'     => $this->normalizeType($column->Type),
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
     * Asserts that `parent_id` is a nullable bigint with a self-referencing foreign key
     * that sets the column to NULL when the parent nutrient is deleted.
     */
    public function test_nutrients_table_has_parent_id_column(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM nutrients");
        $column = collect($columnsInfo)->firstWhere('Field', 'parent_id');

        $this->assertNotNull($column, "Column 'parent_id' does not exist");
        $this->assertSame('YES', $column->Null, "Column 'parent_id' should be nullable");
        $this->assertStringStartsWith('bigint', strtolower($column->Type));

        $fks = DB::select("
            SELECT kcu.CONSTRAINT_NAME, rc.DELETE_RULE
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                AND rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
            WHERE kcu.TABLE_SCHEMA = DATABASE()
              AND kcu.TABLE_NAME = 'nutrients'
              AND kcu.COLUMN_NAME = 'parent_id'
              AND kcu.REFERENCED_TABLE_NAME = 'nutrients'
        ");

        $this->assertNotEmpty($fks, "Foreign key on 'parent_id' referencing 'nutrients.id' should exist");
        $this->assertEquals('SET NULL', $fks[0]->DELETE_RULE, "FK should use SET NULL on delete");
    }

    /**
     * Asserts that `slug` is a nullable varchar with a unique index applied to it.
     */
    public function test_nutrients_table_has_slug_column(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM nutrients");
        $column = collect($columnsInfo)->firstWhere('Field', 'slug');

        $this->assertNotNull($column, "Column 'slug' does not exist");
        $this->assertSame('YES', $column->Null, "Column 'slug' should be nullable");
        $this->assertStringStartsWith('varchar', strtolower($column->Type));

        $indexes = DB::select("
            SELECT INDEX_NAME
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'nutrients'
              AND COLUMN_NAME = 'slug'
              AND NON_UNIQUE = 0
        ");

        $this->assertNotEmpty($indexes, "A unique index on 'slug' should exist");
    }

    /**
     * Asserts that `canonical_unit_id` is a nullable bigint with a foreign key referencing
     * `units.id` that sets the column to NULL when the referenced unit is deleted.
     */
    public function test_nutrients_table_has_canonical_unit_id_column(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM nutrients");
        $column = collect($columnsInfo)->firstWhere('Field', 'canonical_unit_id');

        $this->assertNotNull($column, "Column 'canonical_unit_id' does not exist");
        $this->assertSame('YES', $column->Null, "Column 'canonical_unit_id' should be nullable");
        $this->assertStringStartsWith('bigint', strtolower($column->Type));

        $fks = DB::select("
            SELECT kcu.CONSTRAINT_NAME, rc.DELETE_RULE
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                AND rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
            WHERE kcu.TABLE_SCHEMA = DATABASE()
              AND kcu.TABLE_NAME = 'nutrients'
              AND kcu.COLUMN_NAME = 'canonical_unit_id'
              AND kcu.REFERENCED_TABLE_NAME = 'units'
        ");

        $this->assertNotEmpty($fks, "Foreign key on 'canonical_unit_id' referencing 'units.id' should exist");
        $this->assertEquals('SET NULL', $fks[0]->DELETE_RULE, "FK should use SET NULL on delete");
    }

    /**
     * Asserts that `iu_to_canonical_factor` is a nullable decimal column used to convert
     * IU-based values to the canonical unit.
     */
    public function test_nutrients_table_has_iu_to_canonical_factor_column(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM nutrients");
        $column = collect($columnsInfo)->firstWhere('Field', 'iu_to_canonical_factor');

        $this->assertNotNull($column, "Column 'iu_to_canonical_factor' does not exist");
        $this->assertSame('YES', $column->Null, "Column 'iu_to_canonical_factor' should be nullable");
        $this->assertStringStartsWith('decimal', strtolower($column->Type));
    }

    /**
     * Asserts that `is_label_standard` is a non-nullable tinyint that defaults to 0 (false),
     * indicating whether the nutrient appears on standard nutrition labels.
     */
    public function test_nutrients_table_has_is_label_standard_column(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM nutrients");
        $column = collect($columnsInfo)->firstWhere('Field', 'is_label_standard');

        $this->assertNotNull($column, "Column 'is_label_standard' does not exist");
        $this->assertSame('NO', $column->Null, "Column 'is_label_standard' should not be nullable");
        $this->assertStringStartsWith('tinyint', strtolower($column->Type));
        $this->assertEquals('0', $column->Default, "Column 'is_label_standard' should default to 0 (false)");
    }

    /**
     * Asserts that `display_order` is a nullable integer column used to control
     * the presentation order of nutrients in UI contexts.
     */
    public function test_nutrients_table_has_display_order_column(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM nutrients");
        $column = collect($columnsInfo)->firstWhere('Field', 'display_order');

        $this->assertNotNull($column, "Column 'display_order' does not exist");
        $this->assertSame('YES', $column->Null, "Column 'display_order' should be nullable");
        $this->assertStringStartsWith('int', strtolower($column->Type));
    }

    /**
     * Asserts that multiple NULL slugs are permitted (MySQL treats NULLs as distinct in unique indexes)
     * while duplicate non-null slugs are rejected by the unique index.
     */
    public function test_slug_partial_unique_index(): void
    {
        $base = [
            'source'     => 'USDA',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Multiple NULL slugs must be allowed (MySQL treats NULLs as distinct in unique indexes)
        DB::table('nutrients')->insert($base + ['name' => 'Protein',  'slug' => null]);
        DB::table('nutrients')->insert($base + ['name' => 'Fat',      'slug' => null]);
        $this->assertEquals(2, DB::table('nutrients')->whereNull('slug')->count());

        // A unique non-null slug is accepted
        DB::table('nutrients')->insert($base + ['name' => 'Fiber', 'slug' => 'fiber']);

        // Duplicate non-null slug must be rejected
        $this->expectException(QueryException::class);
        DB::table('nutrients')->insert($base + ['name' => 'Dietary Fiber', 'slug' => 'fiber']);
    }

    /**
     * Asserts that calling `down()` on the canonical columns migration drops all six added columns,
     * and that `up()` re-creates them successfully.
     */
    public function test_migration_rolls_back_cleanly(): void
    {
        $newColumns = ['parent_id', 'slug', 'canonical_unit_id', 'iu_to_canonical_factor', 'is_label_standard', 'display_order'];

        foreach ($newColumns as $col) {
            $this->assertTrue(Schema::hasColumn('nutrients', $col), "Column '{$col}' should exist before rollback");
        }

        $migration = include database_path('migrations/2026_04_13_000002_add_canonical_columns_to_nutrients_table.php');
        $migration->down();

        foreach ($newColumns as $col) {
            $this->assertFalse(Schema::hasColumn('nutrients', $col), "Column '{$col}' should be gone after rollback");
        }

        $migration->up();
    }

    /**
     * Asserts that the unique constraint on (source, external_id, name) rejects duplicate rows.
     */
    public function test_unique_constraint_works_for_source_external_id_and_name(): void
    {
        $data = [
            'source'      => 'USDA',
            'external_id' => 203,
            'name'        => 'Protein',
            'created_at'  => now(),
            'updated_at'  => now(),
        ];

        DB::table('nutrients')->insert($data);

        $this->expectException(QueryException::class);

        DB::table('nutrients')->insert($data);
    }

    /**
     * Asserts that a nutrient can be inserted without an `external_id` and that the column stores NULL.
     */
    public function test_allows_nullable_external_id(): void
    {
        DB::table('nutrients')->insert([
            'source'     => 'USDA',
            'external_id' => null,
            'name'        => 'Fiber',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $row = DB::table('nutrients')->where('name', 'Fiber')->first();
        $this->assertNotNull($row);
        $this->assertNull($row->external_id);
    }

    /**
     * Strips the size specifier from a MySQL column type string (e.g. `bigint(20)` → `bigint`).
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
