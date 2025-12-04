<?php
namespace App\Data\USDAFoodData;

use App\Data\DataTransferObject;
use App\Models\IngredientNutritionFact;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;

class UsdaIngredientNutritionFactData extends DataTransferObject
{
    protected ?array $sourceNutrients;
    
    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->sourceNutrients = $data;

    }

    public function toArray(): array
    {
        $nutritionFacts = [];

        // Normalize the source nutrient names for easier matching
        $sourceNutrientsByName = [];
        foreach ($this->sourceNutrients as $sourceNutrient) {
            $name = trim($sourceNutrient['nutrient']['name']);
            $sourceNutrientsByName[$name] = $sourceNutrient;
        }

        // Loop through canonical nutrient keys (the ones you want in the final array)
        $mappings = $this->makeNutritionFactsMappings();
        foreach ($mappings as $internalName => $sourceEntries) {
            $totalAmount = 0;
            $baseUnit = null;

            foreach ($sourceEntries as $source) {
                $sourceName = $source['name'];

                if (isset($sourceNutrientsByName[$sourceName])) {
                    $nutrient = $sourceNutrientsByName[$sourceName];
                    $amount = $nutrient['amount'] ?? 0;
                    $unitName = $nutrient['nutrient']['unitName'] ?? null;

                    if ($baseUnit === null) {
                        $baseUnit = $unitName;
                    }

                    // Convert to same unit if needed
                    $convertedAmount = $this->convertToCommonUnit($amount, $unitName, $baseUnit);
                    $totalAmount += $convertedAmount;
                }
            }

            if ($totalAmount > 0) {
                $nutritionFacts[] = $this->makeNutritionFact([
                    'category' => $this->resolveCategory($internalName),
                    'name' => $internalName,
                    'amount' => $totalAmount,
                    'amount_unit_id' => $this->resolveUnitId($baseUnit),
                ]);
            }
        }
        return $nutritionFacts;
    }

    public function toModel(): Model
    {
        return new IngredientNutritionFact($this->toArray());
    }

    protected function makeNutritionFact(array $data): array
    {
        return [
            'category'       => $data['category'] ?? 'other',
            'name'           => $data['name'] ?? null,
            'amount'         => $data['amount'] ?? 0,
            'amount_unit_id' => $data['amount_unit_id'] ?? null,
        ];
    }
    
    protected function convertToCommonUnit(float $amount, ?string $fromUnit, ?string $targetUnit): float
    {
        $factors = [
            'µg' => 0.000001,
            'mcg' => 0.000001,
            'mg' => 0.001,
            'g'  => 1,
            'kJ' => 1, // leave energy as-is
            'kcal' => 1,
            'IU' => 1,
        ];

        if (!$fromUnit || !$targetUnit || $fromUnit === $targetUnit) {
            return $amount;
        }

        if (!isset($factors[$fromUnit]) || !isset($factors[$targetUnit])) {
            return $amount; // fallback: unknown unit
        }

        // convert both to grams for mass-based nutrients
        $grams = $amount * $factors[$fromUnit];
        return $grams / $factors[$targetUnit];
    }

    protected function resolveCategory(string $name): string
    {
        $map = [
            // === MACRONUTRIENTS ===
            'protein' => 'macronutrient',
            'carbohydrates' => 'macronutrient',
            'fat' => 'macronutrient',
            'fiber (total)' => 'macronutrient',
            'fiber (soluble)' => 'macronutrient',
            'fiber (insoluble)' => 'macronutrient',

            // === ENERGY ===
            'calories' => 'energy',
            'joules' => 'energy',

            // === VITAMINS ===
            'Vitamin A' => 'vitamin',
            'Vitamin B1 (Thiamine)' => 'vitamin',
            'Vitamin B2 (Riboflavin)' => 'vitamin',
            'Vitamin B3 (Niacin)' => 'vitamin',
            'Vitamin B5 (Pantothenic acid)' => 'vitamin',
            'Vitamin B6 (Pyridoxine)' => 'vitamin',
            'Vitamin B7 (Biotin)' => 'vitamin',
            'Vitamin B9 (Folate)' => 'vitamin',
            'Vitamin B12 (Cobalamin)' => 'vitamin',
            'Vitamin C (Ascorbic acid)' => 'vitamin',
            'Vitamin D2 (Ergocalciferol)' => 'vitamin',
            'Vitamin D3 (Cholecalciferol)' => 'vitamin',
            'Vitamin E (Alpha-tocopherol)' => 'vitamin',
            'Vitamin K1 (Phylloquinone)' => 'vitamin',
            'Vitamin K2 (Menaquinone)' => 'vitamin',

            // === MINERALS ===
            // Macrominerals
            'Calcium' => 'mineral',
            'Chloride' => 'mineral',
            'Magnesium' => 'mineral',
            'Phosphorus' => 'mineral',
            'Potassium' => 'mineral',
            'Sodium' => 'mineral',
            'Sulfur' => 'mineral',

            // Trace minerals (microminerals)
            'Iron' => 'mineral',
            'Zinc' => 'mineral',
            'Copper' => 'mineral',
            'Manganese' => 'mineral',
            'Selenium' => 'mineral',
            'Iodine' => 'mineral',
            'Chromium' => 'mineral',
            'Molybdenum' => 'mineral',
            'Fluoride' => 'mineral',
            'Cobalt' => 'mineral',
            'Nickel' => 'mineral',
            'Silicon' => 'mineral',
            'Vanadium' => 'mineral',
        ];

        return $map[$name] ?? 'other';
    }

    protected function resolveUnitId(string $unitName): ?int
    {
        $unit = Unit::where(['abbreviation' => $unitName])->first();
        return $unit->id ?? null;
    }
    
    protected function makeNutritionFactsMappings(): array {
        return [
            // Macronutrients
            'protein' => [
                ['name' => 'Protein'],
            ],

            'carbohydrates' => [
                ['name' => 'Carbohydrate, by difference'],
                ['name' => 'Carbohydrate, by summation'],
                ['name' => 'Starch'],
                ['name' => 'Resistant starch'],
                ['name' => 'Total Sugars'],
                ['name' => 'Sugars, Total'],
                ['name' => 'Glucose'],
                ['name' => 'Fructose'],
                ['name' => 'Sucrose'],
                ['name' => 'Maltose'],
                ['name' => 'Lactose'],
                ['name' => 'Galactose'],
                ['name' => 'Raffinose'],
                ['name' => 'Stachyose'],
            ],

            'fat' => [
                ['name' => 'Total lipid (fat)'],
                ['name' => 'Total fat (NLEA)'],
                ['name' => 'Fatty acids, total saturated'],
                ['name' => 'Fatty acids, total monounsaturated'],
                ['name' => 'Fatty acids, total polyunsaturated'],
                ['name' => 'Fatty acids, total trans'],
                ['name' => 'Cholesterol'],
            ],

            'fiber (total)' => [
                ['name' => 'Fiber, total dietary'],
                ['name' => 'Total dietary fiber (AOAC 2011.25)'],
                ['name' => 'High Molecular Weight Dietary Fiber (HMWDF)'],
                ['name' => 'Low Molecular Weight Dietary Fiber (LMWDF)'],
            ],

            'fiber (soluble)' => [
                ['name' => 'Fiber, soluble'],
            ],

            'fiber (insoluble)' => [
                ['name' => 'Fiber, insoluble'],
            ],

            // Energy
            'calories' => [
                ['name' => 'Energy'],
                ['name' => 'Energy (Atwater General Factors)'],
                ['name' => 'Energy (Atwater Specific Factors)'],
            ],

            'joules' => [
                ['name' => 'Energy'], // same source, different unit
            ],

            // Vitamins
            'Vitamin A' => [
                ['name' => 'Vitamin A'],
                ['name' => 'Vitamin A, RAE'],
                ['name' => 'Retinol'],
                ['name' => 'Carotene, beta'],
                ['name' => 'Carotene, alpha'],
                ['name' => 'Cryptoxanthin, beta'],
                ['name' => 'Cryptoxanthin, alpha'],
                ['name' => 'Lycopene'],
                ['name' => 'Lutein + zeaxanthin'],
            ],

            'Vitamin B1 (Thiamine)' => [
                ['name' => 'Thiamin'],
            ],

            'Vitamin B2 (Riboflavin)' => [
                ['name' => 'Riboflavin'],
            ],

            'Vitamin B3 (Niacin)' => [
                ['name' => 'Niacin'],
            ],

            'Vitamin B5 (Pantothenic acid)' => [
                ['name' => 'Pantothenic acid'],
            ],

            'Vitamin B6 (Pyridoxine)' => [
                ['name' => 'Vitamin B-6'],
            ],

            'Vitamin B7 (Biotin)' => [
                ['name' => 'Biotin'],
            ],

            'Vitamin B9 (Folate)' => [
                ['name' => 'Folate, total'],
                ['name' => '5-methyl tetrahydrofolate (5-MTHF)'],
                ['name' => '10-Formyl folic acid (10HCOFA)'],
                ['name' => '5-Formyltetrahydrofolic acid (5-HCOH4'],
            ],

            'Vitamin B12 (Cobalamin)' => [
                ['name' => 'Vitamin B-12'],
            ],

            'Vitamin C (Ascorbic acid)' => [
                ['name' => 'Vitamin C, total ascorbic acid'],
            ],

            'Vitamin D2 (Ergocalciferol)' => [
                ['name' => 'Vitamin D2 (ergocalciferol)'],
            ],

            'Vitamin D3 (Cholecalciferol)' => [
                ['name' => 'Vitamin D3 (cholecalciferol)'],
                ['name' => 'Vitamin D (D2 + D3)'],
                ['name' => 'Vitamin D (D2 + D3), International Units'],
                ['name' => '25-hydroxycholecalciferol'],
                ['name' => 'Vitamin D4'], // sometimes included in total D
            ],

            'Vitamin E (Alpha-tocopherol)' => [
                ['name' => 'Vitamin E (alpha-tocopherol)'],
                ['name' => 'Tocopherol, beta'],
                ['name' => 'Tocopherol, gamma'],
                ['name' => 'Tocopherol, delta'],
                ['name' => 'Tocotrienol, alpha'],
                ['name' => 'Tocotrienol, beta'],
                ['name' => 'Tocotrienol, gamma'],
                ['name' => 'Tocotrienol, delta'],
            ],

            'Vitamin K1 (Phylloquinone)' => [
                ['name' => 'Vitamin K (phylloquinone)'],
            ],

            'Vitamin K2 (Menaquinone)' => [
                ['name' => 'Vitamin K (Menaquinone-4)'],
            ],

            // Minerals (Macro)
            'Calcium' => [
                ['name' => 'Calcium, Ca'],
            ],
            'Chloride' => [
                // none in source list, can remain empty or map later
            ],
            'Magnesium' => [
                ['name' => 'Magnesium, Mg'],
            ],
            'Phosphorus' => [
                ['name' => 'Phosphorus, P'],
            ],
            'Potassium' => [
                ['name' => 'Potassium, K'],
            ],
            'Sodium' => [
                ['name' => 'Sodium, Na'],
            ],
            'Sulfur' => [
                ['name' => 'Sulfur, S'],
            ],

            // Trace minerals
            'Iron' => [
                ['name' => 'Iron, Fe'],
            ],
            'Zinc' => [
                ['name' => 'Zinc, Zn'],
            ],
            'Copper' => [
                ['name' => 'Copper, Cu'],
            ],
            'Manganese' => [
                ['name' => 'Manganese, Mn'],
            ],
            'Selenium' => [
                ['name' => 'Selenium, Se'],
            ],
            'Iodine' => [
                ['name' => 'Iodine, I'],
            ],
            'Chromium' => [
                // none found in your list
            ],
            'Molybdenum' => [
                ['name' => 'Molybdenum, Mo'],
            ],
            'Fluoride' => [
                // none in source
            ],
            'Cobalt' => [
                ['name' => 'Cobalt, Co'],
            ],
            'Nickel' => [
                ['name' => 'Nickel, Ni'],
            ],
            'Silicon' => [
                // none
            ],
            'Vanadium' => [
                // none
            ]
        ];
    }
}





// Desired output:
// [
//     // Macronutrients
//     [
//         'category' => 'macronutrients',
//         'name' => 'protein',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'macronutrients',
//         'name' => 'carbohydrates',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'macronutrients',
//         'name' => 'fat',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'macronutrients',
//         'name' => 'fiber (total)',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'macronutrients',
//         'name' => 'fiber (soluble)',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'macronutrients',
//         'name' => 'fiber (insoluble)',
//         'amount' => '',
//         'amount_unit_id',
//     ],
// 
//     // Energy
//     [
//         'category' => 'energy',
//         'name' => 'calories',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'energy',
//         'name' => 'joules',
//         'amount' => '',
//         'amount_unit_id',
//     ],
// 
//     // Vitamins (Micronutrients)
//     [
//         'category' => 'micronutrients',
//         'name' => 'Vitamin A',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'micronutrients',
//         'name' => 'Vitamin B1 (Thiamine)',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'micronutrients',
//         'name' => 'Vitamin B2 (Riboflavin)',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'micronutrients',
//         'name' => 'Vitamin B3 (Niacin)',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'micronutrients',
//         'name' => 'Vitamin B5 (Pantothenic acid)',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'micronutrients',
//         'name' => 'Vitamin B6 (Pyridoxine)',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'micronutrients',
//         'name' => 'Vitamin B7 (Biotin)',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'micronutrients',
//         'name' => 'Vitamin B9 (Folate)',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'micronutrients',
//         'name' => 'Vitamin B12 (Cobalamin)',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'micronutrients',
//         'name' => 'Vitamin C (Ascorbic acid)',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'micronutrients',
//         'name' => 'Vitamin D2 (Ergocalciferol)',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'micronutrients',
//         'name' => 'Vitamin D3 (Cholecalciferol)',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'micronutrients',
//         'name' => 'Vitamin E (Alpha-tocopherol)',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'micronutrients',
//         'name' => 'Vitamin K1 (Phylloquinone)',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'micronutrients',
//         'name' => 'Vitamin K2 (Menaquinone)',
//         'amount' => '',
//         'amount_unit_id',
//     ],
// 
//     // Minerals (Macrominerals)
//     [
//         'category' => 'minerals',
//         'name' => 'Calcium',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'minerals',
//         'name' => 'Chloride',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'minerals',
//         'name' => 'Magnesium',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'minerals',
//         'name' => 'Phosphorus',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'minerals',
//         'name' => 'Potassium',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'minerals',
//         'name' => 'Sodium',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'minerals',
//         'name' => 'Sulfur',
//         'amount' => '',
//         'amount_unit_id',
//     ],
// 
//     // Trace minerals (Microminerals)
//     [
//         'category' => 'minerals',
//         'name' => 'Iron',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'minerals',
//         'name' => 'Zinc',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'minerals',
//         'name' => 'Copper',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'minerals',
//         'name' => 'Manganese',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'minerals',
//         'name' => 'Selenium',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'minerals',
//         'name' => 'Iodine',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'minerals',
//         'name' => 'Chromium',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'minerals',
//         'name' => 'Molybdenum',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'minerals',
//         'name' => 'Fluoride',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'minerals',
//         'name' => 'Cobalt',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'minerals',
//         'name' => 'Nickel',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'minerals',
//         'name' => 'Silicon',
//         'amount' => '',
//         'amount_unit_id',
//     ],
//     [
//         'category' => 'minerals',
//         'name' => 'Vanadium',
//         'amount' => '',
//         'amo
//     ]
// ]