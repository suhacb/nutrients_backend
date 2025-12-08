<?php

namespace Tests\Feature\Ingredients;

use Tests\TestCase;
use App\Models\Ingredient;
use Illuminate\Support\Facades\DB;
use Database\Seeders\UnitsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ImportIngredientsCommandTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
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
    }
}
