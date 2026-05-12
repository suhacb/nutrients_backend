<?php

namespace Tests\Unit\Recipes;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecipeDietTagPivotMigrationTest extends TestCase
{
    use RefreshDatabase;

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

    private function insertDietTag(string $slug = 'keto'): int
    {
        return DB::table('diet_tags')->insertGetId([
            'name'       => ucfirst($slug),
            'slug'       => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_recipe_diet_tag_table_has_expected_columns(): void
    {
        $columnsInfo = DB::select('SHOW COLUMNS FROM recipe_diet_tag');
        $columns = collect($columnsInfo)->pluck('Field')->toArray();

        $this->assertContains('recipe_id', $columns);
        $this->assertContains('diet_tag_id', $columns);
    }

    public function test_foreign_keys_exist(): void
    {
        $foreignKeys = DB::select("
            SELECT COLUMN_NAME, REFERENCED_TABLE_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'recipe_diet_tag'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        $refs = collect($foreignKeys)->mapWithKeys(
            fn($fk) => [$fk->COLUMN_NAME => $fk->REFERENCED_TABLE_NAME]
        )->toArray();

        $this->assertArrayHasKey('recipe_id', $refs);
        $this->assertEquals('recipes', $refs['recipe_id']);

        $this->assertArrayHasKey('diet_tag_id', $refs);
        $this->assertEquals('diet_tags', $refs['diet_tag_id']);
    }

    public function test_composite_primary_key_rejects_duplicate_pairs(): void
    {
        $recipeId  = $this->insertRecipe();
        $dietTagId = $this->insertDietTag();

        DB::table('recipe_diet_tag')->insert([
            'recipe_id'   => $recipeId,
            'diet_tag_id' => $dietTagId,
        ]);

        $this->expectException(QueryException::class);

        DB::table('recipe_diet_tag')->insert([
            'recipe_id'   => $recipeId,
            'diet_tag_id' => $dietTagId,
        ]);
    }

    public function test_same_recipe_can_have_multiple_diet_tags(): void
    {
        $recipeId   = $this->insertRecipe();
        $ketoId     = $this->insertDietTag('keto');
        $veganId    = $this->insertDietTag('vegan');

        DB::table('recipe_diet_tag')->insert([
            ['recipe_id' => $recipeId, 'diet_tag_id' => $ketoId],
            ['recipe_id' => $recipeId, 'diet_tag_id' => $veganId],
        ]);

        $count = DB::table('recipe_diet_tag')->where('recipe_id', $recipeId)->count();
        $this->assertEquals(2, $count);
    }

    public function test_same_diet_tag_can_belong_to_multiple_recipes(): void
    {
        $recipeA   = $this->insertRecipe('recipe-a');
        $recipeB   = $this->insertRecipe('recipe-b');
        $dietTagId = $this->insertDietTag();

        DB::table('recipe_diet_tag')->insert([
            ['recipe_id' => $recipeA, 'diet_tag_id' => $dietTagId],
            ['recipe_id' => $recipeB, 'diet_tag_id' => $dietTagId],
        ]);

        $count = DB::table('recipe_diet_tag')->where('diet_tag_id', $dietTagId)->count();
        $this->assertEquals(2, $count);
    }

    public function test_pivot_rows_cascade_delete_when_recipe_is_deleted(): void
    {
        $recipeId  = $this->insertRecipe();
        $dietTagId = $this->insertDietTag();

        DB::table('recipe_diet_tag')->insert([
            'recipe_id'   => $recipeId,
            'diet_tag_id' => $dietTagId,
        ]);

        DB::table('recipes')->where('id', $recipeId)->delete();

        $count = DB::table('recipe_diet_tag')->where('recipe_id', $recipeId)->count();
        $this->assertEquals(0, $count, 'Pivot rows should be removed when the recipe is deleted');
    }

    public function test_pivot_rows_cascade_delete_when_diet_tag_is_deleted(): void
    {
        $recipeId  = $this->insertRecipe();
        $dietTagId = $this->insertDietTag();

        DB::table('recipe_diet_tag')->insert([
            'recipe_id'   => $recipeId,
            'diet_tag_id' => $dietTagId,
        ]);

        DB::table('diet_tags')->where('id', $dietTagId)->delete();

        $count = DB::table('recipe_diet_tag')->where('diet_tag_id', $dietTagId)->count();
        $this->assertEquals(0, $count, 'Pivot rows should be removed when the diet tag is deleted');
    }
}
