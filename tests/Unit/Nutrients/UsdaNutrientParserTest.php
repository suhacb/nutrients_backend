<?php

namespace Tests\Unit\Nutrients;

use Tests\TestCase;
use App\Models\Nutrient;
use App\Parsers\USDA\UsdaNutrientsParser;

class UsdaNutrientParserTest extends TestCase
{
    public function test_parse_returns_collection_of_nutrient_models()
    {
        // Sample USDA-like data

        $data = $this->makeData();
        $parser = new UsdaNutrientsParser();

        // Use the parse shortcut
        $result = $parser->parse($data);

        // Assert we get a Collection
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);

        // Assert all items are Nutrient instances
        $result->each(function ($item) {
            $this->assertInstanceOf(Nutrient::class, $item);
        });

        // Assert uniqueness and values
        $names = $result->pluck('name')->all();
        $this->assertEquals(['Protein', 'Total lipid (fat)', 'Carbohydrate, by difference'], $names);

        // Check external_ids
        $externalIds = $result->pluck('external_id')->all();
        $this->assertEquals(['203', '204', '205'], $externalIds);

        // Check derivation fields
        $protein = $result->first(fn($n) => $n->external_id === '203');
        $this->assertEquals('LCCD', $protein->derivation_code);
        $this->assertEquals('Calculated from amino acids', $protein->derivation_description);
    }

    private function makeData(): array
    {
        return [
            'FoundationFoods' => [
                [
                    'description' => 'Apple',
                    'foodNutrients' => [
                        [
                            'nutrient' => [
                                'number' => '203',
                                'name' => 'Protein',
                                'derivation_code' => 'LCCD',
                                'derivation_description' => 'Calculated from amino acids',
                            ]
                        ],
                        [
                            'nutrient' => [
                                'number' => '204',
                                'name' => 'Total lipid (fat)',
                            ]
                        ],
                    ],
                ],
                [
                    'description' => 'Banana',
                    'foodNutrients' => [
                        [
                            'nutrient' => [
                                'number' => '203',
                                'name' => 'Protein',
                            ]
                        ],
                        [
                            'nutrient' => [
                                'number' => '205',
                                'name' => 'Carbohydrate, by difference',
                            ]
                        ],
                    ],
                ],
            ],
        ];
    }
}
