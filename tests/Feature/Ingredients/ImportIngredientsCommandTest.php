<?php

namespace Tests\Feature\Ingredients;

use Tests\TestCase;
use App\Models\Ingredient;
use Illuminate\Support\Facades\DB;
use Database\Seeders\UnitsTableSeeder;

class ImportIngredientsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->seed(UnitsTableSeeder::class);
    }

    public function test_import_ingredients_command_reads_actual_file_and_inserts_data(): void
    {
        // Path to your real USDA JSON file
        $filePath = base_path('storage/app/private/FoodData_Central_foundation_food_json_2025-04-24.json');

        // Make sure file exists
        $this->assertFileExists($filePath, "USDA JSON file not found at {$filePath}");

        // Act: run the console command
        $exitCode = $this->artisan('app:import-ingredients', [
            'file' => $filePath,
            '--parser' => 'USDA',
        ]);

        // Assert: command completes successfully
        $exitCode->assertExitCode(0);

        // Assert category was created
        $this->assertDatabaseHas('ingredient_categories', [
            'name' => 'Legumes and Legume Products'
        ]);

        // Assert ingredient was created
        $this->assertDatabaseHas('ingredients', [
            'name' => 'Hummus, commercial',
        ]);

        // Example assertions: check if at least one ingredient and category were created
        $this->assertTrue(DB::table('ingredient_categories')->count() >= 1);
        $this->assertTrue(DB::table('ingredients')->count() >= 1);

        // Optional: check first ingredient pivot
        $ingredient = Ingredient::first();
        $this->assertNotNull($ingredient);

        $this->assertTrue(
            $ingredient->categories()->exists(),
            'Ingredient should be attached to at least one category'
        );
    }

    protected function tearDown(): void
    {
        // $this->artisan('migrate:fresh');
        parent::tearDown();
    }
}
