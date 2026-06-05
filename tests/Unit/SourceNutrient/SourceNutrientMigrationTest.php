<?php

namespace Tests\Unit\SourceNutrient;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SourceNutrientMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_nutrients_table_has_expected_columns(): void
    {
        $expected = [
            'id', 'source_id', 'external_id', 'name', 'description',
            'canonical_unit_id', 'nutrient_id', 'resolved_at', 'created_at', 'updated_at',
        ];

        foreach ($expected as $column) {
            $this->assertTrue(
                Schema::hasColumn('source_nutrients', $column),
                "Column '{$column}' missing from source_nutrients"
            );
        }
    }

    public function test_unique_constraint_on_source_id_and_external_id(): void
    {
        $sourceId = DB::table('sources')->insertGetId(['name' => 'USDA', 'slug' => 'usda-' . uniqid(), 'created_at' => now(), 'updated_at' => now()]);

        DB::table('source_nutrients')->insert(['source_id' => $sourceId, 'external_id' => '1001', 'name' => 'Protein', 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('source_nutrients')->insert(['source_id' => $sourceId, 'external_id' => '1001', 'name' => 'Protein', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_nutrient_id_is_nullable(): void
    {
        $sourceId = DB::table('sources')->insertGetId(['name' => 'Test', 'slug' => 'test-' . uniqid(), 'created_at' => now(), 'updated_at' => now()]);

        $id = DB::table('source_nutrients')->insertGetId(['source_id' => $sourceId, 'external_id' => '999', 'name' => 'Novel', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertNull(DB::table('source_nutrients')->where('id', $id)->value('nutrient_id'));
    }

    public function test_ingredient_source_nutrient_table_has_expected_columns(): void
    {
        $expected = ['ingredient_id', 'source_nutrient_id', 'amount', 'amount_unit_id', 'created_at', 'updated_at'];

        foreach ($expected as $column) {
            $this->assertTrue(
                Schema::hasColumn('ingredient_source_nutrient', $column),
                "Column '{$column}' missing from ingredient_source_nutrient"
            );
        }
    }

    public function test_nutrient_source_mappings_table_does_not_exist(): void
    {
        $this->assertFalse(
            Schema::hasTable('nutrient_source_mappings'),
            'nutrient_source_mappings should have been dropped by migration'
        );
    }

    public function test_nutrient_mapping_reviews_has_source_nutrient_id_not_nutrient_id(): void
    {
        $this->assertTrue(Schema::hasColumn('nutrient_mapping_reviews', 'source_nutrient_id'));
        $this->assertFalse(Schema::hasColumn('nutrient_mapping_reviews', 'nutrient_id'));
    }
}
