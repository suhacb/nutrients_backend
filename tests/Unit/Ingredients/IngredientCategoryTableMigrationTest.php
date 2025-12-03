<?php

namespace Tests\Unit\Ingredients;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IngredientCategoryTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected array $expectedColumns = [
        'id' => ['type' => 'bigint unsigned', 'nullable' => false],
        'name' => ['type' => 'varchar(255)', 'nullable' => false],
        'created_at' => ['type' => 'timestamp', 'nullable' => true],
        'updated_at' => ['type' => 'timestamp', 'nullable' => true],
    ];

    public function test_ingredient_categories_table_has_expected_columns_with_correct_attributes(): void
    {
        $this->assertTrue(
            Schema::hasTable('ingredient_categories'),
            'ingredient_categories table does not exist'
        );

        foreach ($this->expectedColumns as $column => $meta) {
            $this->assertTrue(
                Schema::hasColumn('ingredient_categories', $column),
                "Column {$column} is missing in ingredient_categories"
            );

            // Optional: check nullable property
            $columnInfo = DB::selectOne("SHOW COLUMNS FROM `ingredient_categories` WHERE Field = ?", [$column]);

            $isNullable = strtolower($columnInfo->Null) === 'yes';
            $this->assertEquals(
                $meta['nullable'],
                $isNullable,
                "Column {$column} nullable mismatch. Expected: {$meta['nullable']}, got: {$isNullable}"
            );

            // Optional: check type (MySQL-specific)
            $this->assertStringContainsString(
                $meta['type'],
                strtolower($columnInfo->Type),
                "Column {$column} type mismatch. Expected to contain: {$meta['type']}, got: {$columnInfo->Type}"
            );
        }
    }

    public function test_ingredient_category_name_is_unique_and_not_nullable(): void
    {
        // Insert first valid record
        $id1 = DB::table('ingredient_categories')->insertGetId([
            'name' => 'Fruits',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNotNull($id1, 'Failed to insert first ingredient category');

        // Inserting a duplicate name should fail
        $this->expectException(QueryException::class);

        DB::table('ingredient_categories')->insert([
            'name' => 'Fruits', // duplicate
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_name_column_cannot_be_null(): void
    {
        $this->expectException(QueryException::class);

        DB::table('ingredient_categories')->insert([
            'name' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
