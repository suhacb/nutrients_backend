<?php

namespace App\Console\Commands;

use Exception;
use Throwable;
use JsonMachine\Items;
use App\Models\Nutrient;
use App\Models\Ingredient;
use Illuminate\Console\Command;
use App\Models\IngredientCategory;
use Illuminate\Support\Facades\DB;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
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

    protected $baseKeys = [
        'FoundationFoods',
        'SRLegacyFoods',
        'BrandedFoods'
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {   
        $this->info('Max execution time: ' . ini_get('max_execution_time'));
        $filePath = $this->argument('file');
        $baseKey = $this->detectBaseKey($filePath);
        if (!$baseKey) {
            $this->error("Source file doesn't contain any of the allowed base keys.");
        }

        $sourceIngredients = $this->readDataFromFile($filePath, $baseKey);
        return 0;
    }

    private function readDataFromFile(string $filePath, string $baseKey): int
    {
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }
        $this->info("Streaming JSON file: {$filePath}");
        $items = Items::fromFile($filePath, [
            'pointer' => "/{$baseKey}",
            'decoder' => new ExtJsonDecoder(true)
            ]);

        $count = 0;
        foreach ($items as $sourceIngredient) {
            $count++;
            $sourceIngredientArray = (array) $sourceIngredient;
            $ingredient = new UsdaIngredientData($sourceIngredientArray);
            $this->importFromUsdaArray($ingredient->toArray());
            if ($count % 100 === 0) {
                $this->info("Imported {$count} ingredients...");
            }
        }

        $this->info("Finished import. Total ingredients: {$count}");
        return 0;
    }

    public function importFromUsdaArray(array $data)
    {
        $transactionable = !app()->environment('testing');
        try {
            if ($transactionable) {
                DB::transaction(function() use ($data) {
                    $this->import($data);
                });
            } else {
                $this->import($data);
            }
        } catch (\Throwable $e) {
            logger()->error("Transaction rolled back for ingredient {$data['ingredient']['name']}: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // rethrow to propagate error to handle() or stop import
        }
    }

    private function import(array $data): void
    {
        if ($data['category']['name']) {
            $category = IngredientCategory::firstOrCreate([
                'name' => $data['category']['name'],
            ]);
        } else {
            $category = null;
        }

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

        if ($category) {
            if (!$ingredient->categories()->where('ingredient_category_id', $category->id)->exists()) {
                $ingredient->categories()->attach($category->id);
            }
        }

        if ($ingredient->wasRecentlyCreated) {
            $this->attachNutrients($ingredient, $data);
        }

        if ($ingredient->wasRecentlyCreated) {
            $this->createNutritionFacts($ingredient, $data['nutrition_facts']);
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

    private function detectBaseKey(string $filePath): ?string
    {
        // read the first few kilobytes (enough to get the root key)
        $handle = fopen($filePath, 'r');
        $chunk = fread($handle, 1024); // 1 KB
        fclose($handle);

        $handle = fopen($filePath, 'r');
        $chunk = fread($handle, 16384); // 16 KB from start
        fclose($handle);

        // Look for one of the known root keys immediately after a JSON object start
        foreach ($this->baseKeys as $key) {
            if (preg_match('/"\s*' . preg_quote($key, '/') . '\s*"\s*:/', $chunk)) {
                return $key;
            }
        }

        return null;
    }
}
