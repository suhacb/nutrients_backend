<?php

namespace Tests\Unit\Recipes;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecipesMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected array $expectedColumns = [
        'id'           => ['type' => 'bigint',    'nullable' => false],
        'name'         => ['type' => 'text',      'nullable' => false],
        'slug'         => ['type' => 'varchar',   'nullable' => false],
        'description'  => ['type' => 'text',      'nullable' => true],
        'instructions' => ['type' => 'text',      'nullable' => true],
        'portions'     => ['type' => 'smallint',  'nullable' => false],
        'source_url'   => ['type' => 'varchar',   'nullable' => true],
        'sync_status'  => ['type' => 'varchar',   'nullable' => false],
        'created_at'   => ['type' => 'timestamp', 'nullable' => true],
        'updated_at'   => ['type' => 'timestamp', 'nullable' => true],
        'deleted_at'   => ['type' => 'timestamp', 'nullable' => true],
    ];

    public function test_recipes_table_has_expected_columns(): void
    {
        $columnsInfo = DB::select('SHOW COLUMNS FROM recipes');

        $columns = [];
        foreach ($columnsInfo as $column) {
            $columns[$column->Field] = [
                'type'     => $this->normalizeType($column->Type),
                'nullable' => $column->Null === 'YES',
            ];
        }

        foreach ($this->expectedColumns as $name => $details) {
            $this->assertArrayHasKey($name, $columns, "Column '{$name}' does not exist in recipes");
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

    public function test_recipes_slug_has_unique_index(): void
    {
        $indexes = DB::select("
            SELECT INDEX_NAME
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'recipes'
              AND COLUMN_NAME = 'slug'
              AND NON_UNIQUE = 0
        ");

        $this->assertNotEmpty($indexes, "A unique index on 'slug' should exist in recipes");
    }

    public function test_slug_unique_constraint_rejects_duplicates(): void
    {
        DB::table('recipes')->insert([
            'name'        => 'Pasta Bolognese',
            'slug'        => 'pasta-bolognese',
            'portions'    => 4,
            'sync_status' => 'pending',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('recipes')->insert([
            'name'        => 'Pasta Bolognese Duplicate',
            'slug'        => 'pasta-bolognese',
            'portions'    => 2,
            'sync_status' => 'pending',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function test_can_insert_recipe_with_all_fields(): void
    {
        $id = DB::table('recipes')->insertGetId([
            'name'         => 'Chicken Salad',
            'slug'         => 'chicken-salad',
            'description'  => 'A light summer salad.',
            'instructions' => "## Steps\n1. Grill chicken.\n2. Toss with greens.",
            'portions'     => 2,
            'source_url'   => 'https://example.com/chicken-salad',
            'sync_status'  => 'pending',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $row = DB::table('recipes')->find($id);

        $this->assertNotNull($row);
        $this->assertEquals('Chicken Salad', $row->name);
        $this->assertEquals('chicken-salad', $row->slug);
        $this->assertEquals('A light summer salad.', $row->description);
        $this->assertStringContainsString('Grill chicken', $row->instructions);
        $this->assertEquals(2, $row->portions);
        $this->assertEquals('https://example.com/chicken-salad', $row->source_url);
        $this->assertEquals('pending', $row->sync_status);
    }

    public function test_nullable_fields_accept_null(): void
    {
        $id = DB::table('recipes')->insertGetId([
            'name'        => 'Simple Recipe',
            'slug'        => 'simple-recipe',
            'portions'    => 1,
            'sync_status' => 'pending',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $row = DB::table('recipes')->find($id);

        $this->assertNull($row->description);
        $this->assertNull($row->instructions);
        $this->assertNull($row->source_url);
        $this->assertNull($row->deleted_at);
    }

    public function test_portions_defaults_to_one(): void
    {
        $id = DB::table('recipes')->insertGetId([
            'name'        => 'Default Portions Recipe',
            'slug'        => 'default-portions-recipe',
            'sync_status' => 'pending',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $row = DB::table('recipes')->find($id);

        $this->assertEquals(1, $row->portions);
    }

    public function test_supports_soft_deletes_via_deleted_at(): void
    {
        $id = DB::table('recipes')->insertGetId([
            'name'        => 'Soft Delete Recipe',
            'slug'        => 'soft-delete-recipe',
            'portions'    => 1,
            'sync_status' => 'pending',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::table('recipes')->where('id', $id)->update(['deleted_at' => now()]);

        $row = DB::table('recipes')->find($id);

        $this->assertNotNull($row->deleted_at);
    }

    public function test_migration_rolls_back_cleanly(): void
    {
        $this->assertTrue(Schema::hasTable('recipes'));

        // Pivot tables reference recipes; roll them back first
        $ingredientPivot = include database_path('migrations/2026_05_12_044559_create_recipe_ingredient_table.php');
        $ingredientPivot->down();

        $dietTagPivot = include database_path('migrations/2026_05_12_044544_create_recipe_diet_tag_table.php');
        $dietTagPivot->down();

        $migration = include database_path('migrations/2026_05_12_044523_create_recipes_table.php');
        $migration->down();

        $this->assertFalse(Schema::hasTable('recipes'));

        $migration->up();
        $this->assertTrue(Schema::hasTable('recipes'));

        $dietTagPivot->up();
        $ingredientPivot->up();
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
