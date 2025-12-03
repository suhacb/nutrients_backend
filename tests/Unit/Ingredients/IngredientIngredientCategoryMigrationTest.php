<?php

namespace Tests\Unit\Ingredients;

use Tests\TestCase;
use App\Models\Ingredient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IngredientIngredientCategoryMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected array $expectedColumns = [
        'ingredient_id' => ['type' => 'bigint unsigned', 'nullable' => false],
        'ingredient_category_id' => ['type' => 'bigint unsigned', 'nullable' => false],
    ];

    public function test_pivot_table_exists_and_has_expected_columns(): void
    {
        $this->assertTrue(
            Schema::hasTable('ingredient_ingredient_category'),
            'Pivot table ingredient_ingredient_category does not exist'
        );

        foreach ($this->expectedColumns as $column => $meta) {
            $this->assertTrue(
                Schema::hasColumn('ingredient_ingredient_category', $column),
                "Column {$column} is missing in pivot table"
            );

            $columnInfo = DB::selectOne("SHOW COLUMNS FROM `ingredient_ingredient_category` WHERE Field = ?", [$column]);

            $isNullable = strtolower($columnInfo->Null) === 'yes';
            $this->assertEquals(
                $meta['nullable'],
                $isNullable,
                "Column {$column} nullable mismatch. Expected: {$meta['nullable']}, got: {$isNullable}"
            );

            $this->assertStringContainsString(
                $meta['type'],
                strtolower($columnInfo->Type),
                "Column {$column} type mismatch. Expected to contain: {$meta['type']}, got: {$columnInfo->Type}"
            );
        }
    }

    public function test_pivot_table_composite_primary_key_exists(): void
    {
        $indexes = DB::select("SHOW KEYS FROM `ingredient_ingredient_category` WHERE Key_name = 'PRIMARY'");
        $columns = array_map(fn($idx) => $idx->Column_name, $indexes);

        $this->assertContains('ingredient_id', $columns, 'ingredient_id is missing from composite primary key');
        $this->assertContains('ingredient_category_id', $columns, 'ingredient_category_id is missing from composite primary key');
    }

    public function test_pivot_table_enforces_foreign_keys(): void
    {
        // Insert valid ingredient & category
        $ingredient = Ingredient::factory()->create();
        $categoryId = DB::table('ingredient_categories')->insertGetId(['name' => 'Test Category']);

        // Valid pivot insertion
        $inserted = DB::table('ingredient_ingredient_category')->insert([
            'ingredient_id' => $ingredient->id,
            'ingredient_category_id' => $categoryId,
        ]);
        $this->assertTrue($inserted);

        // Invalid ingredient_id
        $this->expectException(QueryException::class);
        DB::table('ingredient_ingredient_category')->insert([
            'ingredient_id' => 999999,
            'ingredient_category_id' => $categoryId,
        ]);
    }

    public function test_pivot_table_prevents_duplicate_entries(): void
    {
        $ingredient = Ingredient::factory()->create();
        $categoryId = DB::table('ingredient_categories')->insertGetId(['name' => 'Test Category 2']);

        DB::table('ingredient_ingredient_category')->insert([
            'ingredient_id' => $ingredient->id,
            'ingredient_category_id' => $categoryId,
        ]);

        // Duplicate insert should fail due to composite primary key
        $this->expectException(QueryException::class);
        DB::table('ingredient_ingredient_category')->insert([
            'ingredient_id' => $ingredient->id,
            'ingredient_category_id' => $categoryId,
        ]);
    }
}
