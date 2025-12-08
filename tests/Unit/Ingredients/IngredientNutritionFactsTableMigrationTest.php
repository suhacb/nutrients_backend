<?php

namespace Tests\Unit\Ingredients;

use Tests\TestCase;
use App\Models\Unit;
use App\Models\Ingredient;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\MakesUnit;

class IngredientNutritionFactsTableMigrationTest extends TestCase
{
    use RefreshDatabase, MakesUnit;

    public function setUp(): void
    {
        parent::setUp();
    }

    protected $expectedColumns = [
        'id' => ['type' => 'bigint unsigned', 'nullable' => false],
        'ingredient_id' => ['type' => 'bigint unsigned', 'nullable' => false],
        'category' => ['type' => 'varchar(255)', 'nullable' => false],
        'name' => ['type' => 'varchar(255)', 'nullable' => false],
        'amount' => ['type' => 'double', 'nullable' => false],
        'amount_unit_id' => ['type' => 'bigint unsigned', 'nullable' => false],
        'created_at' => ['type' => 'timestamp', 'nullable' => true],
        'updated_at' => ['type' => 'timestamp', 'nullable' => true],
    ];

    public function test_ingredient_nutrients_table_has_expected_columns(): void
    {
        $columnsInfo = DB::select("SHOW COLUMNS FROM ingredient_nutrition_facts");

        $columns = [];
        foreach ($columnsInfo as $column) {
            $columns[$column->Field] = [
                'type' => strtolower($column->Type),
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

    public function test_unique_constraint_works_for_ingredient_category_and_name(): void
    {
        $ingredient = Ingredient::factory()->create();
        $unit = $this->makeUnit();

        // Insert first nutrient
        DB::table('ingredient_nutrition_facts')->insert([
            'ingredient_id' => $ingredient->id,
            'category' => 'macro',
            'name' => 'Protein',
            'amount' => 10.0,
            'amount_unit_id' => $unit->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        // Insert duplicate nutrient for same ingredient + category + name → should fail
        DB::table('ingredient_nutrition_facts')->insert([
            'ingredient_id' => $ingredient->id,
            'category' => 'macro',
            'name' => 'Protein',
            'amount' => 12.0,
            'amount_unit_id' => $unit->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_allows_different_categories_or_names_for_same_ingredient(): void
    {
        $ingredient = Ingredient::factory()->create();
        $unit = $this->makeUnit();

        // Macro: Protein
        DB::table('ingredient_nutrition_facts')->insert([
            'ingredient_id' => $ingredient->id,
            'category' => 'macro',
            'name' => 'Protein',
            'amount' => 10.0,
            'amount_unit_id' => $unit->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Macro: Fat
        DB::table('ingredient_nutrition_facts')->insert([
            'ingredient_id' => $ingredient->id,
            'category' => 'macro',
            'name' => 'Fat',
            'amount' => 5.0,
            'amount_unit_id' => $unit->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Micro: Vitamin A
        DB::table('ingredient_nutrition_facts')->insert([
            'ingredient_id' => $ingredient->id,
            'category' => 'micro',
            'name' => 'Vitamin A',
            'amount' => 0.2,
            'amount_unit_id' => $unit->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows = DB::table('ingredient_nutrition_facts')->where('ingredient_id', $ingredient->id)->get();
        $this->assertCount(3, $rows);
    }
}
