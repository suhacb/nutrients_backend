<?php

namespace Tests\Unit\Nutrients;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NutrientsMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected $expectedColumns = [
        'id' => ['type' => 'bigint', 'nullable' => false],
        'source' => ['type' => 'varchar', 'nullable' => false],
        'external_id' => ['type' => 'bigint', 'nullable' => true],
        'name' => ['type' => 'varchar', 'nullable' => false],
        'description' => ['type' => 'text', 'nullable' => true],
        'derivation_code' => ['type' => 'varchar', 'nullable' => true],
        'derivation_description' => ['type' => 'varchar', 'nullable' => true],
        'created_at' => ['type' => 'timestamp', 'nullable' => true],
        'updated_at' => ['type' => 'timestamp', 'nullable' => true],
        'deleted_at' => ['type' => 'timestamp', 'nullable' => true]
    ];

    public function test_nutrients_table_has_expected_columns(): void
    {
        // Get MySQL columns info
        $columnsInfo = DB::select("SHOW COLUMNS FROM nutrients");

        // Convert to associative array keyed by column name
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

    // Helper to normalize MySQL type (strip size, e.g., bigint(20) → bigint)
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
            'external_id' => 203,
            'name' => 'Protein',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Insert a row
        DB::table('nutrients')->insert($data);

        $this->expectException(QueryException::class);

        // Insert duplicate row → should fail
        DB::table('nutrients')->insert($data);
    }

    public function test_allows_nullable_external_id(): void
    {
        DB::table('nutrients')->insert([
            'source' => 'USDA',
            'external_id' => null,
            'name' => 'Fiber',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('nutrients')->where('name', 'Fiber')->first();
        $this->assertNotNull($row);
        $this->assertNull($row->external_id);
    }
}
