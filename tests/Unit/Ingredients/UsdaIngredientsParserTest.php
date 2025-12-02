<?php

namespace Tests\Unit\Ingredients;

use Tests\TestCase;
use App\Models\Ingredient;
use App\Models\Nutrient;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use App\Parsers\USDA\UsdaIngredientsParser;
use Database\Seeders\UnitsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UsdaIngredientsParserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('app:import-nutrients', [
            'file' => storage_path('app/private/FoodData_Central_foundation_food_json_2025-04-24.json'),
            '--parser' => 'USDA',
        ]);
        $this->seed(UnitsTableSeeder::class);
    }
    
    public function test_parse_returns_collection_of_ingredient_models()
    {
        $data = $this->makeSampleData();
        $parser = new UsdaIngredientsParser();

        // Parse the data
        $result = $parser->parse($data);

        // Assert we get a Collection
        $this->assertInstanceOf(Collection::class, $result);

        // Assert all items are Ingredient instances
        $result->each(function ($item) {
            $this->assertInstanceOf(Ingredient::class, $item);
        });
            
        // Assert ingredient fields
        $ingredientNames = $result->pluck('name')->all();
        $this->assertEquals(['Egg, white, raw, frozen, pasteurized'], $ingredientNames);
        
        // Assert foodClass is set
        $foodClasses = $result->pluck('class')->all();
        $this->assertEquals(['FinalFood'], $foodClasses);

        // Assert nutrients are parsed for each ingredient
        $firstIngredientNutrients = $result->first()->nutrients->toArray() ?? [];
        $this->assertIsArray($firstIngredientNutrients);
        $this->assertCount(3, $firstIngredientNutrients); // based on sample below

        // Check a nutrient's name and amount
        $potassium = collect($firstIngredientNutrients)
            ->first(fn($n) => $n['name'] === 'Potassium, K');
        $this->assertEquals(130, $potassium['pivot']->amount);
        $unit = Unit::findOrFail($potassium['pivot']->amount_unit_id);
        $this->assertEquals('mg', $unit->abbreviation);
    }

    private function makeSampleData(): array
    {
        return [
            'FoundationFoods' => [
                [
                    'fdcId' => 323697,
                    'description' => 'Egg, white, raw, frozen, pasteurized',
                    'foodClass' => 'FinalFood',
                    'ndbNumber' => 123,
                    'foodNutrients' => [
                        [
                            'nutrient' => [
                                'id' => 1092,
                                'number' => '306',
                                'name' => 'Potassium, K',
                                'unitName' => 'mg',
                            ],
                            'amount' => 130
                        ],
                        [
                            'nutrient' => [
                                'id' => 1095,
                                'number' => '309',
                                'name' => 'Zinc, Zn',
                                'unitName' => 'mg',
                            ],
                            'amount' => 0.02
                        ],
                        [
                            'nutrient' => [
                                'id' => 1003,
                                'number' => '203',
                                'name' => 'Protein',
                                'unitName' => 'g',
                            ],
                            'amount' => 10.1
                        ],
                    ],
                    'foodPortions' => [
                        [
                            'id' => 119060,
                            'value' => 1,
                            'measureUnit' => [
                                'id' => 1038,
                                'name' => 'oz',
                                'abbreviation' => 'oz'
                            ]
                        ]
                    ]
                ],
            ],
        ];
    }
}
