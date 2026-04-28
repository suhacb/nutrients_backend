<?php

namespace Tests\Unit\Import\Sources\USDA;

use App\Import\Records\ImportBatch;
use App\Import\Records\IngredientCategoryRecord;
use App\Import\Records\IngredientNutrientRecord;
use App\Import\Records\IngredientRecord;
use App\Import\Records\NutrientRecord;
use App\Import\Records\NutritionFactRecord;
use App\Import\Sources\USDA\UsdaImportSource;
use App\Import\Sources\USDA\UsdaIngredientTransformer;
use App\Import\Sources\USDA\UsdaNutrientTransformer;
use App\Import\Sources\USDA\UsdaNutritionFactTransformer;
use App\Import\Sources\USDA\UsdaPivotTransformer;
use PHPUnit\Framework\TestCase;

class UsdaImportSourceTest extends TestCase
{
    private array $unitMap = [
        'g'    => 1,
        'mg'   => 2,
        'µg'   => 3,
        'kcal' => 4,
    ];

    private function makeSource(): UsdaImportSource
    {
        return new UsdaImportSource(
            nutrientTransformer:      new UsdaNutrientTransformer($this->unitMap),
            ingredientTransformer:    new UsdaIngredientTransformer(),
            pivotTransformer:         new UsdaPivotTransformer($this->unitMap),
            nutritionFactTransformer: new UsdaNutritionFactTransformer($this->unitMap),
        );
    }

    private function rawFoundationFood(): array
    {
        return [
            'fdcId'          => 321358,
            'description'    => 'Hummus, commercial',
            'dataType'       => 'Foundation',
            'foodCategory'   => ['description' => 'Legumes and Legume Products'],
            'labelNutrients' => null,
            'foodNutrients'  => [
                [
                    'nutrient' => ['id' => 1003, 'number' => '203', 'name' => 'Protein', 'rank' => 600, 'unitName' => 'g'],
                    'amount'   => 7.9,
                ],
                [
                    'nutrient' => ['id' => 1004, 'number' => '204', 'name' => 'Total lipid (fat)', 'rank' => 800, 'unitName' => 'g'],
                    'amount'   => 5.5,
                ],
            ],
        ];
    }

    private function rawBrandedFood(): array
    {
        return [
            'fdcId'               => 1106281,
            'description'         => 'GRANOLA, CINNAMON, RAISIN',
            'dataType'            => 'Branded',
            'foodCategory'        => null,
            'brandedFoodCategory' => 'Cereal',
            'labelNutrients'      => [
                'protein'  => ['value' => 3.0],
                'calories' => ['value' => 140.0],
            ],
            'foodNutrients' => [
                [
                    'nutrient' => ['id' => 1003, 'number' => '203', 'name' => 'Protein', 'rank' => 600, 'unitName' => 'g'],
                    'amount'   => 10.7,
                ],
            ],
        ];
    }

    public function test_transform_returns_import_batch(): void
    {
        $batch = $this->makeSource()->transform($this->rawFoundationFood());

        $this->assertInstanceOf(ImportBatch::class, $batch);
    }

    public function test_transform_creates_ingredient_record(): void
    {
        $batch = $this->makeSource()->transform($this->rawFoundationFood());

        $this->assertInstanceOf(IngredientRecord::class, $batch->ingredient);
        $this->assertSame('321358', $batch->ingredient->externalId);
        $this->assertSame('Hummus, commercial', $batch->ingredient->name);
    }

    public function test_transform_creates_category_from_food_category(): void
    {
        $batch = $this->makeSource()->transform($this->rawFoundationFood());

        $this->assertInstanceOf(IngredientCategoryRecord::class, $batch->category);
        $this->assertSame('Legumes and Legume Products', $batch->category->name);
    }

    public function test_transform_creates_category_from_branded_food_category(): void
    {
        $batch = $this->makeSource()->transform($this->rawBrandedFood());

        $this->assertSame('Cereal', $batch->category->name);
    }

    public function test_transform_sets_uncategorized_when_no_category_present(): void
    {
        $raw = $this->rawFoundationFood();
        $raw['foodCategory'] = null;
        unset($raw['brandedFoodCategory']);

        $batch = $this->makeSource()->transform($raw);

        $this->assertSame('Uncategorized', $batch->category->name);
    }

    public function test_transform_creates_one_nutrient_record_per_food_nutrient(): void
    {
        $batch = $this->makeSource()->transform($this->rawFoundationFood());

        $this->assertCount(2, $batch->nutrients);
        $this->assertContainsOnlyInstancesOf(NutrientRecord::class, $batch->nutrients);
    }

    public function test_transform_maps_nutrient_fields_correctly(): void
    {
        $batch = $this->makeSource()->transform($this->rawFoundationFood());

        $this->assertSame('203', $batch->nutrients[0]->externalId);
        $this->assertSame('Protein', $batch->nutrients[0]->name);
    }

    public function test_transform_creates_one_pivot_record_per_food_nutrient(): void
    {
        $batch = $this->makeSource()->transform($this->rawFoundationFood());

        $this->assertCount(2, $batch->ingredientNutrients);
        $this->assertContainsOnlyInstancesOf(IngredientNutrientRecord::class, $batch->ingredientNutrients);
    }

    public function test_transform_pivot_links_ingredient_to_nutrient(): void
    {
        $batch = $this->makeSource()->transform($this->rawFoundationFood());

        $pivot = $batch->ingredientNutrients[0];
        $this->assertSame('321358', $pivot->ingredientExternalId);
        $this->assertSame('203', $pivot->nutrientExternalId);
        $this->assertSame(7.9, $pivot->amount);
    }

    public function test_transform_returns_empty_nutrition_facts_when_label_nutrients_null(): void
    {
        $batch = $this->makeSource()->transform($this->rawFoundationFood());

        $this->assertSame([], $batch->nutritionFacts);
    }

    public function test_transform_creates_nutrition_fact_records_from_label_nutrients(): void
    {
        $batch = $this->makeSource()->transform($this->rawBrandedFood());

        $this->assertCount(2, $batch->nutritionFacts);
        $this->assertContainsOnlyInstancesOf(NutritionFactRecord::class, $batch->nutritionFacts);
    }
}
