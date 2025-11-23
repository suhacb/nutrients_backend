<?php

namespace Tests\Unit\Nutrients;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;



class NutrientsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_nutrients_table_has_expected_columns(): void
    {
        $columns = DB::getSchemaBuilder()->getColumnListing('nutrients');

        $expectedColumns = [
            'id',
            'source',
            'external_id',
            'name',
            'description',
            'derivation_code',
            'derivation_description',
            'created_at',
            'updated_at',
            'deleted_at'
        ];

        foreach ($expectedColumns as $column) {
            $this->assertContains($column, $columns, "Column {$column} does not exist in nutrients table");
        }
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
