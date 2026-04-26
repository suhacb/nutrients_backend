<?php

namespace Tests\Unit\Import\Records;

use App\Import\Records\ImportBatch;
use App\Import\Records\IngredientCategoryRecord;
use App\Import\Records\IngredientNutrientRecord;
use App\Import\Records\IngredientRecord;
use App\Import\Records\NutrientRecord;
use App\Import\Records\NutritionFactRecord;
use PHPUnit\Framework\TestCase;

class ImportBatchTest extends TestCase
{
    private function makeNutrientRecord(): NutrientRecord
    {
        return new NutrientRecord('1003', 'Protein', null, null);
    }

    private function makeIngredientRecord(): IngredientRecord
    {
        return new IngredientRecord('171705', 'Whole Milk', null, null, null, null);
    }

    private function makeCategoryRecord(): IngredientCategoryRecord
    {
        return new IngredientCategoryRecord('Dairy and Egg Products');
    }

    public function test_constructor_sets_all_properties(): void
    {
        $nutrient   = $this->makeNutrientRecord();
        $ingredient = $this->makeIngredientRecord();
        $category   = $this->makeCategoryRecord();

        $pivot = new IngredientNutrientRecord('171705', '1003', 3.15, 2);
        $fact  = new NutritionFactRecord('171705', 'Proximates', 'Protein', 3.15, 2);

        $batch = new ImportBatch(
            nutrient:            $nutrient,
            ingredient:          $ingredient,
            category:            $category,
            ingredientNutrients: [$pivot],
            nutritionFacts:      [$fact],
        );

        $this->assertSame($nutrient, $batch->nutrient);
        $this->assertSame($ingredient, $batch->ingredient);
        $this->assertSame($category, $batch->category);
        $this->assertCount(1, $batch->ingredientNutrients);
        $this->assertSame($pivot, $batch->ingredientNutrients[0]);
        $this->assertCount(1, $batch->nutritionFacts);
        $this->assertSame($fact, $batch->nutritionFacts[0]);
    }

    public function test_accepts_empty_ingredient_nutrients_and_nutrition_facts(): void
    {
        $batch = new ImportBatch(
            nutrient:            $this->makeNutrientRecord(),
            ingredient:          $this->makeIngredientRecord(),
            category:            $this->makeCategoryRecord(),
            ingredientNutrients: [],
            nutritionFacts:      [],
        );

        $this->assertSame([], $batch->ingredientNutrients);
        $this->assertSame([], $batch->nutritionFacts);
    }

    public function test_all_properties_are_readonly(): void
    {
        $reflection = new \ReflectionClass(ImportBatch::class);

        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} should be readonly"
            );
        }
    }
}
