<?php

namespace Tests\Unit\Import\Pipeline;

use App\Import\Pipeline\BatchPersistor;
use App\Import\Records\ImportBatch;
use App\Import\Records\IngredientCategoryRecord;
use App\Import\Records\IngredientNutrientRecord;
use App\Import\Records\IngredientRecord;
use App\Import\Records\NutrientRecord;
use App\Import\Records\NutritionFactRecord;
use App\Models\Ingredient;
use App\Models\Nutrient;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\MakesUnit;
use Tests\TestCase;

class BatchPersistorTest extends TestCase
{
    use RefreshDatabase, MakesUnit;

    protected Source $source;
    protected int $unitId;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->source = Source::factory()->create(['slug' => 'usda-food-data-central', 'name' => 'USDA FoodData Central']);
        $this->unitId = $this->makeUnit()->id;
    }

    private function makeBatch(
        string $fdcId = '321358',
        string $nutrientNumber = '203',
        string $categoryName = 'Legumes and Legume Products',
    ): ImportBatch {
        return new ImportBatch(
            ingredient:          new IngredientRecord($fdcId, 'Hummus, commercial', null, 'Foundation', null, null),
            category:            new IngredientCategoryRecord($categoryName),
            nutrients:           [new NutrientRecord($nutrientNumber, 'Protein', null, $this->unitId)],
            ingredientNutrients: [new IngredientNutrientRecord($fdcId, $nutrientNumber, 7.9, $this->unitId)],
            nutritionFacts:      [],
        );
    }

    private function makeBatchWithNutritionFacts(string $fdcId = '1106281'): ImportBatch
    {
        return new ImportBatch(
            ingredient:          new IngredientRecord($fdcId, 'Granola', null, 'Branded', null, null),
            category:            new IngredientCategoryRecord('Cereal'),
            nutrients:           [new NutrientRecord('203', 'Protein', null, $this->unitId)],
            ingredientNutrients: [new IngredientNutrientRecord($fdcId, '203', 3.0, $this->unitId)],
            nutritionFacts:      [new NutritionFactRecord($fdcId, 'Label Nutrients', 'protein', 3.0, $this->unitId)],
        );
    }

    public function test_persists_categories(): void
    {
        (new BatchPersistor())->persist([$this->makeBatch()], $this->source);

        $this->assertDatabaseHas('ingredient_categories', ['name' => 'Legumes and Legume Products']);
    }

    public function test_persists_nutrients_with_source_id(): void
    {
        (new BatchPersistor())->persist([$this->makeBatch()], $this->source);

        $this->assertDatabaseHas('nutrients', [
            'source_id'   => $this->source->id,
            'external_id' => '203',
            'name'        => 'Protein',
        ]);
    }

    public function test_persists_ingredients(): void
    {
        (new BatchPersistor())->persist([$this->makeBatch()], $this->source);

        $this->assertDatabaseHas('ingredients', [
            'external_id' => '321358',
            'name'        => 'Hummus, commercial',
        ]);
    }

    public function test_attaches_category_to_ingredient(): void
    {
        (new BatchPersistor())->persist([$this->makeBatch()], $this->source);

        $ingredient = Ingredient::where('external_id', '321358')->first();
        $this->assertCount(1, $ingredient->categories);
        $this->assertSame('Legumes and Legume Products', $ingredient->categories->first()->name);
    }

    public function test_persists_pivot_records(): void
    {
        (new BatchPersistor())->persist([$this->makeBatch()], $this->source);

        $ingredient = Ingredient::where('external_id', '321358')->first();
        $nutrient   = Nutrient::where('external_id', '203')->first();

        $this->assertDatabaseHas('ingredient_nutrient', [
            'ingredient_id' => $ingredient->id,
            'nutrient_id'   => $nutrient->id,
            'amount'        => 7.9,
            'amount_unit_id'=> $this->unitId,
        ]);
    }

    public function test_persists_nutrition_facts(): void
    {
        (new BatchPersistor())->persist([$this->makeBatchWithNutritionFacts()], $this->source);

        $ingredient = Ingredient::where('external_id', '1106281')->first();

        $this->assertDatabaseHas('ingredient_nutrition_facts', [
            'ingredient_id'  => $ingredient->id,
            'category'       => 'Label Nutrients',
            'name'           => 'protein',
            'amount'         => 3.0,
            'amount_unit_id' => $this->unitId,
        ]);
    }

    public function test_generates_slug_for_nutrients(): void
    {
        (new BatchPersistor())->persist([$this->makeBatch()], $this->source);

        $nutrient = Nutrient::where('external_id', '203')->first();
        $this->assertNotNull($nutrient->slug);
    }

    public function test_generates_slug_for_ingredients(): void
    {
        (new BatchPersistor())->persist([$this->makeBatch()], $this->source);

        $ingredient = Ingredient::where('external_id', '321358')->first();
        $this->assertNotNull($ingredient->slug);
    }

    public function test_skips_pivot_when_nutrient_not_in_batch(): void
    {
        $batch = new ImportBatch(
            ingredient:          new IngredientRecord('321358', 'Hummus, commercial', null, 'Foundation', null, null),
            category:            new IngredientCategoryRecord('Legumes and Legume Products'),
            nutrients:           [],
            ingredientNutrients: [new IngredientNutrientRecord('321358', '999', 5.0, $this->unitId)],
            nutritionFacts:      [],
        );

        (new BatchPersistor())->persist([$batch], $this->source);

        $this->assertDatabaseCount('ingredient_nutrient', 0);
    }

    public function test_is_idempotent(): void
    {
        $persistor = new BatchPersistor();
        $persistor->persist([$this->makeBatch()], $this->source);
        $persistor->persist([$this->makeBatch()], $this->source);

        $this->assertDatabaseCount('nutrients', 1);
        $this->assertDatabaseCount('ingredients', 1);
        $this->assertDatabaseCount('ingredient_nutrient', 1);
    }

    public function test_deduplicates_nutrients_across_batches(): void
    {
        $batch1 = $this->makeBatch('321358', '203');
        $batch2 = $this->makeBatch('171705', '203', 'Dairy and Egg Products');

        (new BatchPersistor())->persist([$batch1, $batch2], $this->source);

        $this->assertDatabaseCount('nutrients', 1);
        $this->assertDatabaseCount('ingredients', 2);
    }
}
