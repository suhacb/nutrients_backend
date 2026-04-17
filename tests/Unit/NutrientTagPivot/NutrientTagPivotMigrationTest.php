<?php

namespace Tests\Unit\NutrientTagPivot;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NutrientNutrientTagPivotMigrationTest extends TestCase
{
    use RefreshDatabase;
        protected array $expectedColumns = [
        'nutrient_id'     => ['type' => 'bigint unsigned', 'nullable' => false],
        'nutrient_tag_id' => ['type' => 'bigint unsigned', 'nullable' => false],
    ];

    /**
     * Asserts the pivot table exists and has the two expected FK columns with correct types and nullability.
     */
    public function test_pivot_table_exists_and_has_expected_columns(): void
    {
        $this->assertTrue(
            Schema::hasTable('nutrient_nutrient_tag'),
            'Pivot table nutrient_nutrient_tag does not exist'
        );
            foreach ($this->expectedColumns as $column => $meta) {
            $this->assertTrue(
                Schema::hasColumn('nutrient_nutrient_tag', $column),
                "Column '{$column}' is missing in pivot table"
            );
                $columnInfo = DB::selectOne(
                "SHOW COLUMNS FROM `nutrient_nutrient_tag` WHERE Field = ?",
                [$column]
            );
                $isNullable = strtolower($columnInfo->Null) === 'yes';
            $this->assertFalse($isNullable, "Column '{$column}' should not be nullable");
                $this->assertStringContainsString(
                $meta['type'],
                strtolower($columnInfo->Type),
                "Column '{$column}' type mismatch. Expected to contain: {$meta['type']}, got: {$columnInfo->Type}"
            );
        }
    }

    /**
     * Asserts no timestamp columns exist — the pivot table is intentionally timestamp-free.
     */
    public function test_pivot_table_has_no_timestamp_columns(): void
    {
        $this->assertFalse(
            Schema::hasColumn('nutrient_nutrient_tag', 'created_at'),
            'Pivot table should not have a created_at column'
        );
        $this->assertFalse(
            Schema::hasColumn('nutrient_nutrient_tag', 'updated_at'),
            'Pivot table should not have an updated_at column'
        );
    }

    /**
     * Asserts both columns form the composite primary key.
     */
    public function test_pivot_table_composite_primary_key_exists(): void
    {
        $indexes = DB::select("SHOW KEYS FROM `nutrient_nutrient_tag` WHERE Key_name = 'PRIMARY'");
        $columns = array_map(fn ($idx) => $idx->Column_name, $indexes);
            $this->assertContains('nutrient_id', $columns, "'nutrient_id' is missing from composite primary key");
        $this->assertContains('nutrient_tag_id', $columns, "'nutrient_tag_id' is missing from composite primary key");
    }

    /**
     * Asserts foreign keys exist referencing nutrients and nutrient_tags.
     */
    public function test_pivot_table_has_foreign_keys(): void
    {
        $foreignKeys = DB::select("
            SELECT COLUMN_NAME, REFERENCED_TABLE_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'nutrient_nutrient_tag'
                AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
            $this->assertNotEmpty($foreignKeys, 'No foreign keys found on nutrient_nutrient_tag');
            $refs = collect($foreignKeys)->pluck('REFERENCED_TABLE_NAME', 'COLUMN_NAME');
            $this->assertEquals('nutrients', $refs->get('nutrient_id'),
            "'nutrient_id' should reference the nutrients table"
        );
        $this->assertEquals('nutrient_tags', $refs->get('nutrient_tag_id'),
                "'nutrient_tag_id' should reference the nutrient_tags table"
            );
    }

    /**
     * Asserts that inserting a pivot row with a non-existent nutrient_id raises a foreign key violation.
     */
    public function test_pivot_table_rejects_invalid_nutrient_id(): void
    {
        $tagId = $this->insertTag('Essential', 'essential');
        $this->expectException(QueryException::class);
        DB::table('nutrient_nutrient_tag')->insert([
            'nutrient_id'     => 999999,
            'nutrient_tag_id' => $tagId,
        ]);
    }

    /**
     * Asserts that inserting a pivot row with a non-existent nutrient_tag_id raises a foreign key violation.
     */
    public function test_pivot_table_rejects_invalid_nutrient_tag_id(): void
    {
        $nutrientId = $this->insertNutrient('Vitamin C');
        $this->expectException(QueryException::class);
        DB::table('nutrient_nutrient_tag')->insert([
            'nutrient_id'     => $nutrientId,
            'nutrient_tag_id' => 999999,
        ]);
    }

    /**
     * Asserts that deleting a nutrient cascades and removes its pivot rows.
     */
    public function test_deleting_nutrient_cascades_to_pivot(): void
    {
        $nutrientId = $this->insertNutrient('Vitamin D');
        $tagId      = $this->insertTag('Fat Soluble', 'fat-soluble');
        DB::table('nutrient_nutrient_tag')->insert([
            'nutrient_id'     => $nutrientId,
            'nutrient_tag_id' => $tagId,
        ]);
        DB::table('nutrients')->where('id', $nutrientId)->delete();
        $this->assertEquals(
            0,
            DB::table('nutrient_nutrient_tag')->where('nutrient_id', $nutrientId)->count(),
            'Pivot rows should be deleted when the nutrient is deleted'
        );
    }

    /**
     * Asserts that deleting a nutrient_tag cascades and removes its pivot rows.
     */
    public function test_deleting_nutrient_tag_cascades_to_pivot(): void
    {
        $nutrientId = $this->insertNutrient('Calcium');
        $tagId      = $this->insertTag('Mineral', 'mineral');
        DB::table('nutrient_nutrient_tag')->insert([
            'nutrient_id'     => $nutrientId,
            'nutrient_tag_id' => $tagId,
        ]);
        DB::table('nutrient_tags')->where('id', $tagId)->delete();
        $this->assertEquals(
            0,
            DB::table('nutrient_nutrient_tag')->where('nutrient_tag_id', $tagId)->count(),
            'Pivot rows should be deleted when the nutrient_tag is deleted'
        );
    }

    /**
     * Asserts the composite primary key prevents inserting the same nutrient–tag pair twice.
     */
    public function test_pivot_table_prevents_duplicate_entries(): void
    {
        $nutrientId = $this->insertNutrient('Iron');
        $tagId      = $this->insertTag('Trace Mineral', 'trace-mineral');
        DB::table('nutrient_nutrient_tag')->insert([
            'nutrient_id'     => $nutrientId,
            'nutrient_tag_id' => $tagId,
        ]);
        $this->expectException(QueryException::class);
        DB::table('nutrient_nutrient_tag')->insert([
            'nutrient_id'     => $nutrientId,
            'nutrient_tag_id' => $tagId,
        ]);
    }

    private function insertNutrient(string $name): int
    {
        $sourceId = DB::table('sources')->insertGetId([
            'name'       => 'Unit Test Source',
            'slug'       => 'unit-test-source',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('nutrients')->insertGetId([
            'name'       => $name,
            'source_id'  => $sourceId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertTag(string $name, string $slug): int
    {
        return DB::table('nutrient_tags')->insertGetId([
            'name'       => $name,
            'slug'       => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}