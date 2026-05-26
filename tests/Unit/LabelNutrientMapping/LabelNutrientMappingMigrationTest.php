<?php

namespace Tests\Unit\LabelNutrientMapping;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Tests\TestCase;
use Tests\MakesUnit;

class LabelNutrientMappingMigrationTest extends TestCase
{
    use RefreshDatabase, MakesUnit;

    private array $expectedColumns = [
        'id'          => ['type' => 'bigint unsigned', 'nullable' => false],
        'label_key'   => ['type' => 'varchar(50)',     'nullable' => false],
        'nutrient_id' => ['type' => 'bigint unsigned', 'nullable' => false],
        'confidence'  => ['type' => 'tinyint unsigned','nullable' => false],
        'reasoning'   => ['type' => 'text',            'nullable' => false],
        'status'      => ['type' => 'enum(\'pending\',\'approved\')', 'nullable' => false],
        'created_at'  => ['type' => 'timestamp',       'nullable' => true],
        'updated_at'  => ['type' => 'timestamp',       'nullable' => true],
    ];

    public function test_label_nutrient_mappings_table_has_expected_columns(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM label_nutrient_mappings");

        $columns = [];
        foreach ($columnsInfo as $column) {
            $columns[$column->Field] = [
                'type'     => strtolower($column->Type),
                'nullable' => $column->Null === 'YES',
            ];
        }

        foreach ($this->expectedColumns as $column => $details) {
            $this->assertArrayHasKey($column, $columns, "Column {$column} does not exist");

            $this->assertEquals(
                $details['type'],
                $columns[$column]['type'],
                "Column {$column} type mismatch"
            );

            $this->assertEquals(
                $details['nullable'],
                $columns[$column]['nullable'],
                "Column {$column} nullable mismatch"
            );
        }
    }

    public function test_label_key_is_unique(): void
    {
        $nutrient = \App\Models\Nutrient::factory()->create(['is_canonical' => true]);

        DB::table('label_nutrient_mappings')->insert([
            'label_key'   => 'protein',
            'nutrient_id' => $nutrient->id,
            'confidence'  => 97,
            'reasoning'   => 'Exact match.',
            'status'      => 'approved',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('label_nutrient_mappings')->insert([
            'label_key'   => 'protein',
            'nutrient_id' => $nutrient->id,
            'confidence'  => 80,
            'reasoning'   => 'Duplicate.',
            'status'      => 'pending',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function test_status_defaults_to_pending(): void
    {
        $nutrient = \App\Models\Nutrient::factory()->create(['is_canonical' => true]);

        DB::table('label_nutrient_mappings')->insert([
            'label_key'   => 'fat',
            'nutrient_id' => $nutrient->id,
            'confidence'  => 90,
            'reasoning'   => 'Likely.',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $row = DB::table('label_nutrient_mappings')->where('label_key', 'fat')->first();
        $this->assertSame('pending', $row->status);
    }

    public function test_different_label_keys_are_allowed(): void
    {
        $nutrient = \App\Models\Nutrient::factory()->create(['is_canonical' => true]);

        DB::table('label_nutrient_mappings')->insert([
            ['label_key' => 'protein', 'nutrient_id' => $nutrient->id, 'confidence' => 97, 'reasoning' => 'Match.', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
            ['label_key' => 'fat',     'nutrient_id' => $nutrient->id, 'confidence' => 97, 'reasoning' => 'Match.', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->assertDatabaseCount('label_nutrient_mappings', 2);
    }
}
