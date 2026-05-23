<?php

namespace Tests\Unit\NutrientSourceMapping;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NutrientSourceMappingMigrationTest extends TestCase
{
    use RefreshDatabase;

    private array $expectedColumns = [
        'id'          => ['type' => 'bigint',    'nullable' => false],
        'nutrient_id' => ['type' => 'bigint',    'nullable' => false],
        'source_id'   => ['type' => 'bigint',    'nullable' => false],
        'external_id' => ['type' => 'varchar',   'nullable' => false],
        'created_at'  => ['type' => 'timestamp', 'nullable' => true],
        'updated_at'  => ['type' => 'timestamp', 'nullable' => true],
    ];

    public function test_nutrient_source_mappings_table_has_expected_columns(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM nutrient_source_mappings");

        $columns = [];
        foreach ($columnsInfo as $column) {
            $columns[$column->Field] = [
                'type'     => $this->normalizeType($column->Type),
                'nullable' => $column->Null === 'YES',
            ];
        }

        foreach ($this->expectedColumns as $column => $details) {
            $this->assertArrayHasKey($column, $columns, "Column '{$column}' does not exist");

            $this->assertEquals(
                $details['type'],
                $columns[$column]['type'],
                "Column '{$column}' type mismatch (expected {$details['type']}, got {$columns[$column]['type']})"
            );

            $this->assertEquals(
                $details['nullable'],
                $columns[$column]['nullable'],
                "Column '{$column}' nullable mismatch (expected " . ($details['nullable'] ? 'YES' : 'NO') . ")"
            );
        }
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
