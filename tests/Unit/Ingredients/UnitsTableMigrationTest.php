<?php

namespace Tests\Unit\Ingredients;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UnitsTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected $expectedColumns = [
        'id' => ['type' => 'bigint', 'nullable' => false],
        'name' => ['type' => 'varchar', 'nullable' => false],
        'abbreviation' => ['type' => 'varchar', 'nullable' => false],
        'type' => ['type' => 'varchar', 'nullable' => true],
        'created_at' => ['type' => 'timestamp', 'nullable' => true],
        'updated_at' => ['type' => 'timestamp', 'nullable' => true],
    ];

    public function test_units_table_has_expected_columns(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM units");

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

    public function test_can_insert_unit(): void
    {
        $unitId = DB::table('units')->insertGetId([
            'name' => 'Gram',
            'abbreviation' => 'g',
            'type' => 'weight',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $unit = DB::table('units')->find($unitId);
        $this->assertNotNull($unit);
        $this->assertEquals('Gram', $unit->name);
        $this->assertEquals('g', $unit->abbreviation);
        $this->assertEquals('weight', $unit->type);
    }

    public function test_allows_nullable_type(): void
    {
        $unitId = DB::table('units')->insertGetId([
            'name' => 'Piece',
            'abbreviation' => 'pc',
            'type' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $unit = DB::table('units')->find($unitId);
        $this->assertNotNull($unit);
        $this->assertNull($unit->type);
    }

    // Helper to normalize MySQL type (strip size, e.g., varchar(255) → varchar)
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
