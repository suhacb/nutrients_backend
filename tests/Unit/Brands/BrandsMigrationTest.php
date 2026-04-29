<?php

namespace Tests\Unit\Brands;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BrandsMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected array $expectedBrandsColumns = [
        'id'          => ['type' => 'bigint',    'nullable' => false],
        'name'        => ['type' => 'text',      'nullable' => false],
        'owner'       => ['type' => 'text',      'nullable' => false],
        'slug'        => ['type' => 'varchar',   'nullable' => false],
        'country'     => ['type' => 'varchar',   'nullable' => true],
        'description' => ['type' => 'text',      'nullable' => true],
        'deleted_at'  => ['type' => 'timestamp', 'nullable' => true],
        'created_at'  => ['type' => 'timestamp', 'nullable' => true],
        'updated_at'  => ['type' => 'timestamp', 'nullable' => true],
    ];

    public function test_brands_table_has_expected_columns(): void
    {
        $columnsInfo = DB::select('SHOW COLUMNS FROM brands');

        $columns = [];
        foreach ($columnsInfo as $column) {
            $columns[$column->Field] = [
                'type'     => $this->normalizeType($column->Type),
                'nullable' => $column->Null === 'YES',
            ];
        }

        foreach ($this->expectedBrandsColumns as $name => $details) {
            $this->assertArrayHasKey($name, $columns, "Column '{$name}' does not exist in brands table");
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

    public function test_brands_slug_has_unique_index(): void
    {
        $indexes = DB::select("
            SELECT INDEX_NAME
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'brands'
              AND COLUMN_NAME = 'slug'
              AND NON_UNIQUE = 0
        ");

        $this->assertNotEmpty($indexes, "A unique index on 'slug' should exist in brands");
    }

    public function test_brands_slug_unique_constraint_rejects_duplicates(): void
    {
        DB::table('brands')->insert([
            'name'       => 'ACME',
            'owner'      => 'ACME Corp',
            'slug'       => 'acme',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('brands')->insert([
            'name'       => 'ACME Duplicate',
            'owner'      => 'ACME Corp',
            'slug'       => 'acme',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_brand_id_foreign_key_cascades_to_null_on_brand_delete(): void
    {
        $brandId = DB::table('brands')->insertGetId([
            'name'       => 'ACME',
            'owner'      => 'ACME Corp',
            'slug'       => 'acme',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $unitId = DB::table('units')->insertGetId([
            'name'       => 'gram',
            'abbreviation' => 'g',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ingredientId = DB::table('ingredients')->insertGetId([
            'name'                   => 'Test Ingredient',
            'source'                 => 'USDA FoodData Central',
            'slug'                   => 'test-ingredient',
            'default_amount'         => 100,
            'default_amount_unit_id' => $unitId,
            'brand_id'               => $brandId,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        DB::table('brands')->where('id', $brandId)->delete();

        $ingredient = DB::table('ingredients')->find($ingredientId);
        $this->assertNull($ingredient->brand_id, "'brand_id' should be NULL after brand deletion");
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
