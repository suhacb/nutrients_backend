<?php

namespace Tests\Unit\NutrientSourcePivot;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NutrientSourceDataMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_id_and_external_id_removed_from_nutrients_table(): void
    {
        $this->assertFalse(Schema::hasColumn('nutrients', 'source_id'), "'source_id' should not exist on nutrients after migration");
        $this->assertFalse(Schema::hasColumn('nutrients', 'external_id'), "'external_id' should not exist on nutrients after migration");
    }

    public function test_existing_source_data_is_copied_to_pivot_on_up(): void
    {
        $migration = include database_path('migrations/2026_05_23_000002_migrate_source_columns_to_nutrient_source_mappings.php');
        $migration->down();

        $sourceId   = \Illuminate\Support\Facades\DB::table('sources')->insertGetId([
            'name'       => 'USDA',
            'slug'       => 'usda-' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $nutrientId = \Illuminate\Support\Facades\DB::table('nutrients')->insertGetId([
            'source_id'   => $sourceId,
            'external_id' => '1001',
            'name'        => 'Protein',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $migration->up();

        $mapping = \Illuminate\Support\Facades\DB::table('nutrient_source_mappings')
            ->where('nutrient_id', $nutrientId)
            ->first();

        $this->assertNotNull($mapping, "Pivot row should have been created during migration");
        $this->assertEquals($sourceId, $mapping->source_id);
        $this->assertEquals('1001', $mapping->external_id);
    }

    public function test_rollback_restores_first_mapping_to_nutrients(): void
    {
        $sourceId   = \Illuminate\Support\Facades\DB::table('sources')->insertGetId([
            'name'       => 'USDA',
            'slug'       => 'usda-' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $nutrientId = \Illuminate\Support\Facades\DB::table('nutrients')->insertGetId([
            'name'       => 'Protein',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Illuminate\Support\Facades\DB::table('nutrient_source_mappings')->insert([
            ['nutrient_id' => $nutrientId, 'source_id' => $sourceId, 'external_id' => '1001', 'created_at' => now(), 'updated_at' => now()],
            ['nutrient_id' => $nutrientId, 'source_id' => $sourceId, 'external_id' => '1002', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migration = include database_path('migrations/2026_05_23_000002_migrate_source_columns_to_nutrient_source_mappings.php');
        $migration->down();

        $nutrient = \Illuminate\Support\Facades\DB::table('nutrients')->where('id', $nutrientId)->first();

        $this->assertEquals($sourceId, $nutrient->source_id, "First mapping's source_id should be restored");
        $this->assertEquals('1001', $nutrient->external_id, "First mapping's external_id should be restored (first wins)");

        $migration->up();
    }

    public function test_nutrients_with_null_external_id_are_not_copied_to_pivot(): void
    {
        $migration = include database_path('migrations/2026_05_23_000002_migrate_source_columns_to_nutrient_source_mappings.php');
        $migration->down();

        $sourceId   = \Illuminate\Support\Facades\DB::table('sources')->insertGetId([
            'name'       => 'USDA',
            'slug'       => 'usda-' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $nutrientId = \Illuminate\Support\Facades\DB::table('nutrients')->insertGetId([
            'source_id'   => $sourceId,
            'external_id' => null,
            'name'        => 'Canonical Fat',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $migration->up();

        $count = \Illuminate\Support\Facades\DB::table('nutrient_source_mappings')
            ->where('nutrient_id', $nutrientId)
            ->count();

        $this->assertEquals(0, $count, "Nutrients with null external_id should not produce a pivot row");
    }
}
