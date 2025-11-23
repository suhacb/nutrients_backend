<?php

namespace Tests\Unit\Nutrients;

use Tests\TestCase;
use App\Models\Nutrient;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ImportNutrientsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_nutrients_command_creates_records(): void
    {
        $file = 'storage/app/private/FoodData_Central_foundation_food_json_2025-04-24.json';
        // Make sure the table is empty
        $this->assertEquals(0, Nutrient::count());

        // Run the command
        Artisan::call('app:import-nutrients', [
            'file' => $file
        ]);

        // Check command output (optional)
        $output = Artisan::output();
        $this->assertStringContainsString('Import completed', $output);

        // Check that nutrients are inserted
        $this->assertGreaterThan(0, Nutrient::count());

        // Example: check one specific nutrient (based on test data)
        $this->assertDatabaseHas('nutrients', [
            'name' => 'Protein',
            'source' => 'USDA FoodData Central',
        ]);
    }

}
