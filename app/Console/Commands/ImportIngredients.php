<?php

namespace App\Console\Commands;

use Exception;
use Throwable;
use App\Models\Nutrient;
use App\Models\Ingredient;
use Illuminate\Console\Command;
use App\Models\IngredientCategory;
use Illuminate\Support\Facades\DB;
use App\Data\USDAFoodData\UsdaIngredientData;

class ImportIngredients extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-ingredients {file} {--parser=USDA}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {   
        $filePath = $this->argument('file');
        $sourceIngredients = $this->readDataFromFile($filePath);

        foreach($sourceIngredients as $index => $sourceIngredient) {
            try {
                $ingredient = new UsdaIngredientData($sourceIngredient);
                $this->importFromUsdaArray($ingredient->toArray());
            } catch (Exception $e) {
                logger()->error("Error importing ingredient at index {$index}: {$e->getMessage()}", [
                    'index' => $index,
                    'sourceIngredient' => $sourceIngredient,
                    // 'trace' => $e->getTraceAsString(),
                ]);
                logger()->error($e->getMessage());
                logger()->error($e->getTraceAsString());
                break;
            }
        }

        return 0;
    }

    private function readDataFromFile(string $filePath): array | int
    {
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }
        $this->info("Reading file: {$filePath}");
        $jsonContent = file_get_contents($filePath);
        $data = json_decode($jsonContent, true);
        unset($jsonContent);
        if ($data === null || !isset($data['FoundationFoods'])) {
            $this->error("Invalid JSON or missing 'FoundationFoods' key");
            return 1;
        }

        return $data['FoundationFoods'];
    }

    public function importFromUsdaArray(array $data)
    {
        try {
            DB::transaction(function () use ($data) {
                $category = IngredientCategory::firstOrCreate([
                    'name' => $data['category']['name'],
                ]);
                logger()->info("Category processed: {$category->name}");

                $ingredientData = $data['ingredient'];

                $ingredient = Ingredient::firstOrCreate(
                    ['name' => $ingredientData['name']],
                    [
                        'source' => $ingredientData['source'],
                        'external_id' => $ingredientData['external_id'],
                        'description' => $ingredientData['description'],
                        'class' => $ingredientData['class'],
                        'default_amount' => $ingredientData['default_amount'],
                        'default_amount_unit_id' => $ingredientData['default_amount_unit_id'],
                    ]
                );
                logger()->info("Ingredient processed: {$ingredient->name}");

                if (!$ingredient->categories()->where('ingredient_category_id', $category->id)->exists()) {
                    $ingredient->categories()->attach($category->id);
                    logger()->info("Category {$category->name} attached to ingredient {$ingredient->name}");
                }

                if ($ingredient->wasRecentlyCreated) {
                    $this->attachNutrients($ingredient, $data);
                    logger()->info("Nutrients attached for ingredient {$ingredient->name}");
                }

                if ($ingredient->wasRecentlyCreated) {
                    $this->createNutritionFacts($ingredient, $data['nutrition_facts']);
                    logger()->info("Nutrition facts attached for ingredient {$ingredient->name}");
                }

                logger()->info("Ingredient transaction complete: {$ingredient->name}");
            });
        } catch (\Throwable $e) {
            logger()->error("Transaction rolled back for ingredient {$data['ingredient']['name']}: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // rethrow to propagate error to handle() or stop import
        }
    }

    private function attachNutrients(Ingredient $ingredient, array $data): void
    {
        if (empty($data['nutrients']) || empty($data['nutrients_pivot'])) return;

        $nutrientsData = $data['nutrients'];
        $nutrientsPivotData = $data['nutrients_pivot'];

         foreach ($data['nutrients'] as $index => $nutrientData) {
            try {
                // Find or create the nutrient
                $nutrient = Nutrient::firstOrCreate([
                    'name' => $nutrientData['name'],
                    'external_id' => $nutrientData['external_id']
                ],
                    $nutrientData
                );
    
                // Attach nutrient to ingredient via pivot
                if (! $ingredient->nutrients()->where('nutrient_id', $nutrient->id)->exists()) {
                    $ingredient->nutrients()->attach($nutrient->id, [
                        'amount' => $nutrientsPivotData[$index]['amount'],
                        'amount_unit_id' => $nutrientsPivotData[$index]['amount_unit_id'] ?? null,
                    ]);
                }
            } catch (Throwable $e) {
                logger()->error("Failed to attach nutrient {$nutrientData['name']} to ingredient {$ingredient->name}: {$e->getMessage()}");
            }
        }
    }

    private function createNutritionFacts(Ingredient $ingredient, array $nutritionFacts): void
    {
        foreach ($nutritionFacts as $fact) {
            try {
                $ingredient->nutrition_facts()->create([
                    'category' => $fact['category'] ?? null,
                    'name' => $fact['name'],
                    'amount' => $fact['amount'] ?? null,
                    'amount_unit_id' => $fact['amount_unit_id'] ?? null,
                ]);
            } catch (Throwable $e) {
                logger()->error("Failed to create nutrition fact {$fact['name']} to ingredient {$ingredient->name}: {$e->getMessage()}");
            }
        }
    }

    private function createMetrics(array $sourceIngredients): array
    {
        $metrics = [
            'expected' => [
                'ingredients' => 0,
                'categories' => [], // ingredient_name => count
                'nutrients' => [],  // ingredient_name => count
                'nutrition_facts' => [], // ingredient_name => count
            ],
            'actual' => [
                'ingredients' => 0,
                'categories' => [], 
                'nutrients' => [],
                'nutrition_facts' => [],
            ],
        ];

        foreach ($sourceIngredients as $ingredient) {
            $name = $ingredient['ingredient']['name'];
            $metrics['expected']['categories'][$name] = isset($ingredient['category']) ? 1 : 0;
            $metrics['expected']['nutrients'][$name] = isset($ingredient['nutrients']) ? count($ingredient['nutrients']) : 0;
            $metrics['expected']['nutrition_facts'][$name] = isset($ingredient['nutrition_facts']) ? count($ingredient['nutrition_facts']) : 0;
        }

        return $metrics;
    }
}
