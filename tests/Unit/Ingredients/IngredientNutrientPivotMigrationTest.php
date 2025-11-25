<?php

namespace Tests\Unit\Ingredients;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IngredientNutrientPivotMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected $expectedColumns = [
        'ingredient_id' => ['type' => 'bigint', 'nullable' => false],
        'nutrient_id' => ['type' => 'bigint', 'nullable' => false],
        'amount' => ['type' => 'double', 'nullable' => false],
        'amount_unit_id' => ['type' => 'bigint', 'nullable' => false],
        'portion_amount' => ['type' => 'double', 'nullable' => true],
        'portion_amount_unit_id' => ['type' => 'bigint', 'nullable' => true],
        'created_at' => ['type' => 'timestamp', 'nullable' => true],
        'updated_at' => ['type' => 'timestamp', 'nullable' => true],
    ];

    public function test_ingredient_nutrient_pivot_has_expected_columns(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM ingredient_nutrient");

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

    public function test_foreign_keys_exist(): void
    {
        $foreignKeys = DB::select("SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'ingredient_nutrient'
            AND REFERENCED_TABLE_NAME IS NOT NULL");

        $this->assertNotEmpty($foreignKeys, "No foreign keys found in ingredient_nutrient table");

        $columns = collect($foreignKeys)->pluck('COLUMN_NAME')->toArray();
        $this->assertContains('ingredient_id', $columns);
        $this->assertContains('nutrient_id', $columns);
        $this->assertContains('amount_unit_id', $columns);
        $this->assertContains('portion_amount_unit_id', $columns);
    }

    public function test_amount_allows_double_values(): void
    {
        $ingredientId = DB::table('ingredients')->insertGetId([
            'name' => 'Test Ingredient',
            'default_amount' => 100,
            'default_amount_unit_id' => 1,
            'source' => 'UNIT_TEST',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $nutrientId = DB::table('nutrients')->insertGetId([
            'name' => 'Test Nutrient',
            'source' => 'UNIT_TEST',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $unitId = DB::table('units')->insertGetId([
            'name' => 'g',
            'abbreviation' => 'g',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ingredient_nutrient')->insert([
            'ingredient_id' => $ingredientId,
            'nutrient_id' => $nutrientId,
            'amount' => 12.34,
            'amount_unit_id' => $unitId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('ingredient_nutrient')->first();
        $this->assertEquals(12.34, $row->amount);
    }

    // Helper to normalize MySQL type (strip size)
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
