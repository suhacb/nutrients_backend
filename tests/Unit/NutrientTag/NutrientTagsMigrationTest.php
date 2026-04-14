<?php

namespace Tests\Unit\NutrientTag;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NutrientTagsMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected $expectedColumns = [
        'id'          => ['type' => 'bigint',    'nullable' => false],
        'name'        => ['type' => 'varchar',   'nullable' => false],
        'slug'        => ['type' => 'varchar',   'nullable' => false],
        'description' => ['type' => 'text',      'nullable' => true],
        'created_at'  => ['type' => 'timestamp', 'nullable' => true],
        'updated_at'  => ['type' => 'timestamp', 'nullable' => true],
    ];

    /**
     * Asserts that every expected column exists in the nutrient_tags table with the correct type and nullability.
     */
    public function test_nutrient_tags_table_has_expected_columns(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM nutrient_tags");

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
    public function test_nutrient_tags_table_has_slug_column(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM nutrient_tags");
        $column = collect($columnsInfo)->firstWhere('Field', 'slug');

        $this->assertNotNull($column, "Column 'slug' does not exist");
        $this->assertSame('NO', $column->Null, "Column 'slug' should not be nullable");
        $this->assertStringStartsWith('varchar', strtolower($column->Type));

        $indexes = DB::select("
            SELECT INDEX_NAME
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'nutrient_tags'
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
        DB::table('nutrient_tags')->insert([
            'name'       => 'Essential',
            'slug'       => 'essential',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('nutrient_tags')->insert([
            'name'       => 'Essential Duplicate',
            'slug'       => 'essential',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Asserts that a nutrient_tag row can be inserted and retrieved with all field values intact.
     */
    public function test_can_insert_nutrient_tag(): void
    {
        $id = DB::table('nutrient_tags')->insertGetId([
            'name'        => 'Electrolyte',
            'slug'        => 'electrolyte',
            'description' => 'Minerals that carry an electric charge.',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $row = DB::table('nutrient_tags')->find($id);

        $this->assertNotNull($row);
        $this->assertEquals('Electrolyte', $row->name);
        $this->assertEquals('electrolyte', $row->slug);
        $this->assertEquals('Minerals that carry an electric charge.', $row->description);
    }

    /**
     * Asserts that a nutrient_tag can be inserted without a `description` and that the column stores NULL.
     */
    public function test_allows_nullable_description(): void
    {
        $id = DB::table('nutrient_tags')->insertGetId([
            'name'       => 'Essential',
            'slug'       => 'essential',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNull(DB::table('nutrient_tags')->find($id)->description);
    }

    /**
     * Asserts that calling `down()` on the migration drops the nutrient_tags table entirely,
     * and that calling `up()` re-creates it successfully.
     */
    public function test_migration_rolls_back_cleanly(): void
    {
        $this->assertTrue(Schema::hasTable('nutrient_tags'), "Table 'nutrient_tags' should exist before rollback");

        $migration = include database_path('migrations/2026_04_13_000004_create_nutrient_tags_table.php');
        $migration->down();

        $this->assertFalse(Schema::hasTable('nutrient_tags'), "Table 'nutrient_tags' should be gone after rollback");

        $migration->up();

        $this->assertTrue(Schema::hasTable('nutrient_tags'), "Table 'nutrient_tags' should be recreated after up()");
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
