<?php
namespace Tests\Unit\Sources;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tests that the sources table migration creates the correct schema.
 *
 * Verifies column presence, types, nullability, the unique index on slug,
 * nullable optional fields, and that the migration rolls back cleanly.
 */
class SourcesTableMigrationTest extends TestCase
{
        use RefreshDatabase;

        protected $expectedColumns = [
            'id'          => ['type' => 'bigint',    'nullable' => false],
            'name'        => ['type' => 'varchar',   'nullable' => false],
            'slug'        => ['type' => 'varchar',   'nullable' => false],
            'url'         => ['type' => 'varchar',   'nullable' => true],
            'description' => ['type' => 'text',      'nullable' => true],
            'created_at'  => ['type' => 'timestamp', 'nullable' => true],
            'updated_at'  => ['type' => 'timestamp', 'nullable' => true],
        ];

    /**
     * Asserts that every expected column exists in the sources table with the correct type and nullability.
     */
    public function test_sources_table_has_expected_columns(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM sources");

        $columns = [];
        foreach ($columnsInfo as $column) {
            $columns[$column->Field] = [
                'type'     => $this->normalizeType($column->Type),
                'nullable' => $column->Null === 'YES',
            ];
        }

        foreach ($this->expectedColumns as $column => $details) {
            $this->assertArrayHasKey($column, $columns, "Column '{$column}' does not exist");

            $this->assertEquals(
                $details['type'],
                $columns[$column]['type'],
                "Column '{$column}' type mismatch (expected {$details['type']}, got {$columns[$column]['type']})"
            );

            $this->assertEquals(
                $details['nullable'],
                $columns[$column]['nullable'],
                "Column '{$column}' nullable mismatch (expected " . ($details['nullable'] ? 'YES' : 'NO') . ")"
            );
        }
    }

    /**
     * Asserts that `slug` is a NOT NULL varchar with a unique index applied to it.
     */
    public function test_sources_table_has_slug_column(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM sources");
        $column = collect($columnsInfo)->firstWhere('Field', 'slug');

        $this->assertNotNull($column, "Column 'slug' does not exist");
        $this->assertSame('NO', $column->Null, "Column 'slug' should not be nullable");
        $this->assertStringStartsWith('varchar', strtolower($column->Type));

        $indexes = DB::select("
            SELECT INDEX_NAME
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'sources'
            AND COLUMN_NAME = 'slug'
            AND NON_UNIQUE = 0
        ");

        $this->assertNotEmpty($indexes, "A unique index on 'slug' should exist");
    }

    /**
     * Asserts that the unique index on `slug` rejects duplicate values.
     */
    public function test_slug_unique_constraint_rejects_duplicates(): void
    {
        DB::table('sources')->insert([
            'name'       => 'USDA FoodData Central',
            'slug'       => 'usda',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('sources')->insert([
            'name'       => 'USDA Duplicate',
            'slug'       => 'usda',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Asserts that a source row can be inserted and retrieved with all field values intact.
     */
    public function test_can_insert_source(): void
    {
        $id = DB::table('sources')->insertGetId([
            'name'        => 'USDA FoodData Central',
            'slug'        => 'usda',
            'url'         => 'https://fdc.nal.usda.gov',
            'description' => 'The USDA national nutrient database.',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $row = DB::table('sources')->find($id);

        $this->assertNotNull($row);
        $this->assertEquals('USDA FoodData Central', $row->name);
        $this->assertEquals('usda', $row->slug);
        $this->assertEquals('https://fdc.nal.usda.gov', $row->url);
        $this->assertEquals('The USDA national nutrient database.', $row->description);
    }

    /**
     * Asserts that a source can be inserted without a `url` and that the column stores NULL.
     */
    public function test_allows_nullable_url(): void
    {
        $id = DB::table('sources')->insertGetId([
            'name'       => 'EFSA',
            'slug'       => 'efsa',
            'url'        => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('sources')->find($id);

        $this->assertNotNull($row);
        $this->assertNull($row->url);
    }

    /**
     * Asserts that a source can be inserted without a `description` and that the column stores NULL.
     */
    public function test_allows_nullable_description(): void
    {
        $id = DB::table('sources')->insertGetId([
            'name'        => 'EFSA',
            'slug'        => 'efsa',
            'description' => null,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $row = DB::table('sources')->find($id);

        $this->assertNotNull($row);
        $this->assertNull($row->description);
    }

    /**
     * Asserts that calling `down()` on the migration drops the sources table entirely,
     * and that calling `up()` re-creates it successfully.
     */
    public function test_migration_rolls_back_cleanly(): void
    {
        $this->assertTrue(Schema::hasTable('sources'), "Table 'sources' should exist before rollback");

        // The replace_source_with_source_id migration adds a FK from nutrients.source_id
        // to sources, so it must be rolled back before sources can be dropped.
        $dependentMigration = include database_path('migrations/2026_04_17_074226_replace_source_with_source_id_on_nutrients_table.php');
        $dependentMigration->down();

        $migration = include database_path('migrations/2026_04_13_164353_create_sources_table.php');
        $migration->down();

        $this->assertFalse(Schema::hasTable('sources'), "Table 'sources' should be gone after rollback");

        $migration->up();

        $this->assertTrue(Schema::hasTable('sources'), "Table 'sources' should be recreated after up()");

        $dependentMigration->up();
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