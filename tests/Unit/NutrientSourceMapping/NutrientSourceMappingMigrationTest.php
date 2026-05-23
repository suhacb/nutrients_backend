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

    public function test_nutrient_id_has_cascading_foreign_key(): void
    {
        $fks = DB::select("
            SELECT rc.DELETE_RULE
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
               AND rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
            WHERE kcu.TABLE_SCHEMA = DATABASE()
              AND kcu.TABLE_NAME = 'nutrient_source_mappings'
              AND kcu.COLUMN_NAME = 'nutrient_id'
              AND kcu.REFERENCED_TABLE_NAME = 'nutrients'
        ");

        $this->assertNotEmpty($fks, "Foreign key on 'nutrient_id' referencing 'nutrients.id' should exist");
        $this->assertEquals('CASCADE', $fks[0]->DELETE_RULE, "FK on 'nutrient_id' should CASCADE on delete");
    }

    public function test_source_id_has_cascading_foreign_key(): void
    {
        $fks = DB::select("
            SELECT rc.DELETE_RULE
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
               AND rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
            WHERE kcu.TABLE_SCHEMA = DATABASE()
              AND kcu.TABLE_NAME = 'nutrient_source_mappings'
              AND kcu.COLUMN_NAME = 'source_id'
              AND kcu.REFERENCED_TABLE_NAME = 'sources'
        ");

        $this->assertNotEmpty($fks, "Foreign key on 'source_id' referencing 'sources.id' should exist");
        $this->assertEquals('CASCADE', $fks[0]->DELETE_RULE, "FK on 'source_id' should CASCADE on delete");
    }

    public function test_unique_constraint_on_source_id_and_external_id(): void
    {
        $sourceId   = $this->insertSource();
        $nutrientId = $this->insertNutrient($sourceId);

        DB::table('nutrient_source_mappings')->insert([
            'nutrient_id' => $nutrientId,
            'source_id'   => $sourceId,
            'external_id' => '1001',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('nutrient_source_mappings')->insert([
            'nutrient_id' => $nutrientId,
            'source_id'   => $sourceId,
            'external_id' => '1001',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function test_same_nutrient_and_source_can_have_multiple_external_ids(): void
    {
        $sourceId   = $this->insertSource();
        $nutrientId = $this->insertNutrient($sourceId);

        DB::table('nutrient_source_mappings')->insert([
            'nutrient_id' => $nutrientId,
            'source_id'   => $sourceId,
            'external_id' => '1001',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::table('nutrient_source_mappings')->insert([
            'nutrient_id' => $nutrientId,
            'source_id'   => $sourceId,
            'external_id' => '1002',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->assertEquals(2, DB::table('nutrient_source_mappings')->where('nutrient_id', $nutrientId)->count());
    }

    public function test_deleting_nutrient_cascades_to_mappings(): void
    {
        $sourceId   = $this->insertSource();
        $nutrientId = $this->insertNutrient($sourceId);

        DB::table('nutrient_source_mappings')->insert([
            'nutrient_id' => $nutrientId,
            'source_id'   => $sourceId,
            'external_id' => '1001',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::table('nutrients')->where('id', $nutrientId)->delete();

        $this->assertEquals(0, DB::table('nutrient_source_mappings')->where('nutrient_id', $nutrientId)->count());
    }

    public function test_deleting_source_cascades_to_mappings(): void
    {
        $nutrientSourceId = $this->insertSource();
        $nutrientId       = $this->insertNutrient($nutrientSourceId);
        $mappingSourceId  = $this->insertSource();

        DB::table('nutrient_source_mappings')->insert([
            'nutrient_id' => $nutrientId,
            'source_id'   => $mappingSourceId,
            'external_id' => '1001',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::table('sources')->where('id', $mappingSourceId)->delete();

        $this->assertEquals(0, DB::table('nutrient_source_mappings')->where('source_id', $mappingSourceId)->count());
    }

    public function test_migration_rolls_back_cleanly(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('nutrient_source_mappings'), "Table should exist before rollback");

        $migration = include database_path('migrations/2026_05_23_000001_create_nutrient_source_mappings_table.php');
        $migration->down();

        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('nutrient_source_mappings'), "Table should be gone after rollback");

        $migration->up();

        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('nutrient_source_mappings'), "Table should exist after re-applying migration");
    }

    private function insertSource(): int
    {
        return DB::table('sources')->insertGetId([
            'name'       => 'USDA FoodData Central',
            'slug'       => 'usda-' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertNutrient(int $sourceId): int
    {
        return DB::table('nutrients')->insertGetId([
            'source_id'  => $sourceId,
            'name'       => 'Protein',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
