<?php

namespace Tests\Unit\DietTag;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DietTagsMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected array $expectedColumns = [
        'id'          => ['type' => 'bigint',    'nullable' => false],
        'name'        => ['type' => 'varchar',   'nullable' => false],
        'slug'        => ['type' => 'varchar',   'nullable' => false],
        'description' => ['type' => 'text',      'nullable' => true],
        'created_at'  => ['type' => 'timestamp', 'nullable' => true],
        'updated_at'  => ['type' => 'timestamp', 'nullable' => true],
    ];

    public function test_diet_tags_table_has_expected_columns(): void
    {
        $columnsInfo = DB::select('SHOW COLUMNS FROM diet_tags');

        $columns = [];
        foreach ($columnsInfo as $column) {
            $columns[$column->Field] = [
                'type'     => $this->normalizeType($column->Type),
                'nullable' => $column->Null === 'YES',
            ];
        }

        foreach ($this->expectedColumns as $name => $details) {
            $this->assertArrayHasKey($name, $columns, "Column '{$name}' does not exist in diet_tags");
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

    public function test_diet_tags_slug_has_unique_index(): void
    {
        $indexes = DB::select("
            SELECT INDEX_NAME
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'diet_tags'
              AND COLUMN_NAME = 'slug'
              AND NON_UNIQUE = 0
        ");

        $this->assertNotEmpty($indexes, "A unique index on 'slug' should exist in diet_tags");
    }

    public function test_slug_unique_constraint_rejects_duplicates(): void
    {
        DB::table('diet_tags')->insert([
            'name'       => 'Keto',
            'slug'       => 'keto',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('diet_tags')->insert([
            'name'       => 'Keto Duplicate',
            'slug'       => 'keto',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_can_insert_diet_tag(): void
    {
        $id = DB::table('diet_tags')->insertGetId([
            'name'        => 'Vegan',
            'slug'        => 'vegan',
            'description' => 'Excludes all animal products.',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $row = DB::table('diet_tags')->find($id);

        $this->assertNotNull($row);
        $this->assertEquals('Vegan', $row->name);
        $this->assertEquals('vegan', $row->slug);
        $this->assertEquals('Excludes all animal products.', $row->description);
    }

    public function test_allows_nullable_description(): void
    {
        $id = DB::table('diet_tags')->insertGetId([
            'name'       => 'Paleo',
            'slug'       => 'paleo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNull(DB::table('diet_tags')->find($id)->description);
    }

    public function test_migration_rolls_back_cleanly(): void
    {
        $this->assertTrue(Schema::hasTable('diet_tags'));

        // recipe_diet_tag references diet_tags, so roll it back first
        $pivotMigration = include database_path('migrations/2026_05_12_044544_create_recipe_diet_tag_table.php');
        $pivotMigration->down();

        $migration = include database_path('migrations/2026_05_12_044509_create_diet_tags_table.php');
        $migration->down();

        $this->assertFalse(Schema::hasTable('diet_tags'));

        $migration->up();
        $this->assertTrue(Schema::hasTable('diet_tags'));

        $pivotMigration->up();
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
