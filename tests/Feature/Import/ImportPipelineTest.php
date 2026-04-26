<?php

namespace Tests\Feature\Import;

use App\Import\Pipeline\BatchPersistor;
use App\Import\Pipeline\ImportPipeline;
use App\Import\Sources\USDA\UsdaImportSource;
use App\Import\Sources\USDA\UsdaIngredientTransformer;
use App\Import\Sources\USDA\UsdaNutrientTransformer;
use App\Import\Sources\USDA\UsdaNutritionFactTransformer;
use App\Import\Sources\USDA\UsdaPivotTransformer;
use App\Jobs\SyncIngredientToSearch;
use App\Jobs\SyncNutrientToSearch;
use App\Models\Ingredient;
use App\Models\Nutrient;
use App\Models\Source;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected string $fixture;
    protected Source $source;
    protected array $unitMap;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->fixture = base_path('tests/fixtures/usda_sample.json');

        $this->source = Source::factory()->create([
            'slug' => 'usda-food-data-central',
            'name' => 'USDA FoodData Central',
        ]);

        Unit::create(['name' => 'gram',      'abbreviation' => 'g',  'type' => 'mass']);
        Unit::create(['name' => 'milligram', 'abbreviation' => 'mg', 'type' => 'mass']);
        Unit::create(['name' => 'kilocalorie','abbreviation' => 'kcal','type' => 'energy']);

        $this->unitMap = Unit::pluck('id', 'abbreviation')->all();
    }

    private function makePipeline(int $batchSize = 100): ImportPipeline
    {
        $source = new UsdaImportSource(
            nutrientTransformer:      new UsdaNutrientTransformer($this->unitMap),
            ingredientTransformer:    new UsdaIngredientTransformer(),
            pivotTransformer:         new UsdaPivotTransformer($this->unitMap),
            nutritionFactTransformer: new UsdaNutritionFactTransformer($this->unitMap),
        );

        return new ImportPipeline(
            source:    $source,
            persistor: new BatchPersistor(),
            batchSize: $batchSize,
        );
    }

    public function test_pipeline_creates_nutrients_from_fixture(): void
    {
        $this->makePipeline()->run($this->fixture);

        $this->assertDatabaseHas('nutrients', ['external_id' => '203', 'name' => 'Protein']);
        $this->assertDatabaseHas('nutrients', ['external_id' => '204', 'name' => 'Total lipid (fat)']);
        $this->assertDatabaseCount('nutrients', 2);
    }

    public function test_pipeline_creates_ingredients_from_fixture(): void
    {
        $this->makePipeline()->run($this->fixture);

        $this->assertDatabaseHas('ingredients', ['external_id' => '321358', 'name' => 'Hummus, commercial']);
        $this->assertDatabaseHas('ingredients', ['external_id' => '171705', 'name' => 'Whole Milk']);
        $this->assertDatabaseCount('ingredients', 2);
    }

    public function test_pipeline_links_nutrients_to_ingredients_via_pivot(): void
    {
        $this->makePipeline()->run($this->fixture);

        $hummus  = Ingredient::where('external_id', '321358')->first();
        $milk    = Ingredient::where('external_id', '171705')->first();
        $protein = Nutrient::where('external_id', '203')->first();
        $fat     = Nutrient::where('external_id', '204')->first();

        $this->assertDatabaseHas('ingredient_nutrient', ['ingredient_id' => $hummus->id, 'nutrient_id' => $protein->id, 'amount' => 7.9]);
        $this->assertDatabaseHas('ingredient_nutrient', ['ingredient_id' => $hummus->id, 'nutrient_id' => $fat->id,     'amount' => 5.5]);
        $this->assertDatabaseHas('ingredient_nutrient', ['ingredient_id' => $milk->id,   'nutrient_id' => $protein->id, 'amount' => 3.2]);
        $this->assertDatabaseCount('ingredient_nutrient', 3);
    }

    public function test_pipeline_deduplicates_nutrients_across_food_items(): void
    {
        $this->makePipeline()->run($this->fixture);

        $this->assertDatabaseCount('nutrients', 2);
    }

    public function test_pipeline_is_idempotent(): void
    {
        $this->makePipeline()->run($this->fixture);
        $this->makePipeline()->run($this->fixture);

        $this->assertDatabaseCount('nutrients', 2);
        $this->assertDatabaseCount('ingredients', 2);
        $this->assertDatabaseCount('ingredient_nutrient', 3);
    }

    public function test_pipeline_dispatches_sync_jobs(): void
    {
        $this->makePipeline()->run($this->fixture);

        Queue::assertPushed(SyncNutrientToSearch::class);
        Queue::assertPushed(SyncIngredientToSearch::class);
    }

    public function test_pipeline_fails_fast_when_source_not_seeded(): void
    {
        $this->source->delete();

        $this->expectException(\RuntimeException::class);

        $this->makePipeline()->run($this->fixture);
    }

    public function test_pipeline_fails_fast_when_file_not_found(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->makePipeline()->run('/nonexistent/file.json');
    }

    public function test_pipeline_skips_records_with_unknown_unit(): void
    {
        $fixture = base_path('tests/fixtures/usda_unknown_unit_sample.json');
        file_put_contents($fixture, json_encode([
            'FoundationFoods' => [
                [
                    'fdcId'          => 999999,
                    'description'    => 'Mystery Food',
                    'dataType'       => 'Foundation',
                    'foodCategory'   => ['description' => 'Unknown'],
                    'labelNutrients' => null,
                    'foodNutrients'  => [
                        [
                            'nutrient' => ['id' => 9999, 'number' => '999', 'name' => 'Unknown Nutrient', 'rank' => 1, 'unitName' => 'xyz'],
                            'amount'   => 1.0,
                        ],
                    ],
                ],
            ],
        ]));

        $this->makePipeline()->run($fixture);

        unlink($fixture);

        $this->assertDatabaseCount('ingredients', 0);
        $this->assertDatabaseCount('nutrients', 0);
    }

    public function test_pipeline_processes_in_batches(): void
    {
        $this->makePipeline(batchSize: 1)->run($this->fixture);

        $this->assertDatabaseCount('ingredients', 2);
        $this->assertDatabaseCount('nutrients', 2);
    }
}
