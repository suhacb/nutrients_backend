<?php

namespace Tests\Unit\Ingredients;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IngredientsMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected $expectedColumns = [
        'id' => ['type' => 'bigint', 'nullable' => false],
        'external_id' => ['type' => 'varchar', 'nullable' => true],
        'source' => ['type' => 'varchar', 'nullable' => false],
        'class' => ['type' => 'varchar', 'nullable' => true],
        'name' => ['type' => 'varchar', 'nullable' => false],
        'description' => ['type' => 'text', 'nullable' => true],
        'default_amount' => ['type' => 'double', 'nullable' => false],
        'default_amount_unit_id' => ['type' => 'bigint', 'nullable' => false],
        'created_at' => ['type' => 'timestamp', 'nullable' => true],
        'updated_at' => ['type' => 'timestamp', 'nullable' => true],
        'deleted_at' => ['type' => 'timestamp', 'nullable' => true],
    ];

    public function test_ingredients_table_has_expected_columns(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM ingredients");

        $columns = [];
        foreach ($columnsInfo as $column) {
            $columns[$column->Field] = [
                'type' => $this->normalizeType($column->Type),
                'nullable' => $column->Null === 'YES',
            ];
        }

        foreach ($this->expectedColumns as $column => $details) {
            $this->assertArrayHasKey($column, $columns, "Column {$column} does not exist");

            $this->assertEquals(
                $details['type'],
                $columns[$column]['type'],
                "Column {$column} type mismatch (expected {$details['type']}, got {$columns[$column]['type']})"
            );

            $this->assertEquals(
                $details['nullable'],
                $columns[$column]['nullable'],
                "Column {$column} nullable mismatch (expected " . ($details['nullable'] ? 'YES' : 'NO') . ")"
            );
        }
    }

    protected function normalizeType(string $type): string
    {
        $type = strtolower($type);
        $matches = [];
        if (preg_match('/^([a-z]+)/', $type, $matches)) {
            return $matches[1];
        }
        return $type;
    }

    public function test_unique_constraint_works_for_source_external_id_and_name(): void
    {
        $data = [
            'source' => 'USDA',
            'external_id' => 11056,
            'name' => 'Beans, snap, green, canned',
            'default_amount' => 100,
            'default_amount_unit_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('ingredients')->insert($data);

        $this->expectException(QueryException::class);

        // Insert duplicate row → should fail
        DB::table('ingredients')->insert($data);
    }

    public function test_allows_nullable_external_id(): void
    {
        DB::table('ingredients')->insert([
            'source' => 'USDA FoodData Central',
            'external_id' => null,
            'name' => 'Carrot',
            'default_amount' => 100,
            'default_amount_unit_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('ingredients')->where('name', 'Carrot')->first();
        $this->assertNotNull($row);
        $this->assertNull($row->external_id);
    }
}
