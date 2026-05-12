<?php

namespace Tests\Unit\Recipes;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecipeIngredientPivotMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected array $expectedColumns = [
        'id'            => ['type' => 'bigint',    'nullable' => false],
        'recipe_id'     => ['type' => 'bigint',    'nullable' => false],
        'ingredient_id' => ['type' => 'bigint',    'nullable' => false],
        'amount'        => ['type' => 'decimal',   'nullable' => false],
        'unit_id'       => ['type' => 'bigint',    'nullable' => false],
        'created_at'    => ['type' => 'timestamp', 'nullable' => true],
        'updated_at'    => ['type' => 'timestamp', 'nullable' => true],
    ];

    private function insertUnit(string $abbreviation = 'g'): int
    {
        return DB::table('units')->insertGetId([
            'name'         => $abbreviation,
            'abbreviation' => $abbreviation,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    private function insertIngredient(int $unitId, string $slug = 'test-ingredient'): int
    {
        return DB::table('ingredients')->insertGetId([
            'name'                   => 'Test Ingredient',
            'slug'                   => $slug,
            'source'                 => 'UNIT_TEST',
            'default_amount'         => 100,
            'default_amount_unit_id' => $unitId,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
    }

    private function insertRecipe(string $slug = 'test-recipe'): int
    {
        return DB::table('recipes')->insertGetId([
            'name'        => 'Test Recipe',
            'slug'        => $slug,
            'portions'    => 2,
            'sync_status' => 'pending',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function test_recipe_ingredient_table_has_expected_columns(): void
    {
        $columnsInfo = DB::select('SHOW COLUMNS FROM recipe_ingredient');

        $columns = [];
        foreach ($columnsInfo as $column) {
            $columns[$column->Field] = [
                'type'     => $this->normalizeType($column->Type),
                'nullable' => $column->Null === 'YES',
            ];
        }

        foreach ($this->expectedColumns as $name => $details) {
            $this->assertArrayHasKey($name, $columns, "Column '{$name}' does not exist in recipe_ingredient");
            $this->assertEquals(
                $details['type'],
                $columns[$name]['type'],
                "Column '{$name}' type mismatch (expected {$details['type']}, got {$columns[$name]['type']})"
            );
            $this->assertEquals(
                $details['nullable'],
                $columns[$name]['nullable'],
                "Column '{$name}' nullable mismatch"
            );
        }
    }

    public function test_foreign_keys_exist(): void
    {
        $foreignKeys = DB::select("
            SELECT COLUMN_NAME, REFERENCED_TABLE_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'recipe_ingredient'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        $refs = collect($foreignKeys)->mapWithKeys(
            fn($fk) => [$fk->COLUMN_NAME => $fk->REFERENCED_TABLE_NAME]
        )->toArray();

        $this->assertArrayHasKey('recipe_id', $refs);
        $this->assertEquals('recipes', $refs['recipe_id']);

        $this->assertArrayHasKey('ingredient_id', $refs);
        $this->assertEquals('ingredients', $refs['ingredient_id']);

        $this->assertArrayHasKey('unit_id', $refs);
        $this->assertEquals('units', $refs['unit_id']);
    }

    public function test_unique_constraint_rejects_duplicate_recipe_ingredient_pair(): void
    {
        $unitId       = $this->insertUnit();
        $recipeId     = $this->insertRecipe();
        $ingredientId = $this->insertIngredient($unitId);

        DB::table('recipe_ingredient')->insert([
            'recipe_id'     => $recipeId,
            'ingredient_id' => $ingredientId,
            'amount'        => 150.0,
            'unit_id'       => $unitId,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('recipe_ingredient')->insert([
            'recipe_id'     => $recipeId,
            'ingredient_id' => $ingredientId,
            'amount'        => 200.0,
            'unit_id'       => $unitId,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_amount_stores_decimal_precision(): void
    {
        $unitId       = $this->insertUnit();
        $recipeId     = $this->insertRecipe();
        $ingredientId = $this->insertIngredient($unitId);

        DB::table('recipe_ingredient')->insert([
            'recipe_id'     => $recipeId,
            'ingredient_id' => $ingredientId,
            'amount'        => 123.4567,
            'unit_id'       => $unitId,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $row = DB::table('recipe_ingredient')->first();
        $this->assertEquals(123.4567, (float) $row->amount);
    }

    public function test_pivot_rows_cascade_delete_when_recipe_is_deleted(): void
    {
        $unitId       = $this->insertUnit();
        $recipeId     = $this->insertRecipe();
        $ingredientId = $this->insertIngredient($unitId);

        DB::table('recipe_ingredient')->insert([
            'recipe_id'     => $recipeId,
            'ingredient_id' => $ingredientId,
            'amount'        => 100.0,
            'unit_id'       => $unitId,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        DB::table('recipes')->where('id', $recipeId)->delete();

        $count = DB::table('recipe_ingredient')->where('recipe_id', $recipeId)->count();
        $this->assertEquals(0, $count, 'Pivot rows should be removed when the recipe is hard-deleted');
    }

    public function test_ingredient_delete_is_restricted_when_used_in_recipe(): void
    {
        $unitId       = $this->insertUnit();
        $recipeId     = $this->insertRecipe();
        $ingredientId = $this->insertIngredient($unitId);

        DB::table('recipe_ingredient')->insert([
            'recipe_id'     => $recipeId,
            'ingredient_id' => $ingredientId,
            'amount'        => 100.0,
            'unit_id'       => $unitId,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('ingredients')->where('id', $ingredientId)->delete();
    }

    protected function normalizeType(string $type): string
    {
        $type = strtolower($type);
        if (preg_match('/^([a-z]+)/', $type, $matches)) {
            return $matches[1];
        }
        return $type;
    }
}
