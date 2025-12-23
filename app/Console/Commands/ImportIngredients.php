<?php

namespace App\Console\Commands;

use Generator;
use RuntimeException;
use JsonMachine\Items;
use App\Models\Nutrient;
use App\Models\Ingredient;
use Illuminate\Console\Command;
use App\Jobs\SyncNutrientToSearch;
use App\Models\IngredientCategory;
use Illuminate\Support\Facades\DB;
use App\Jobs\SyncIngredientToSearch;
use Illuminate\Support\Facades\Storage;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use App\Data\USDAFoodData\UsdaIngredientData;

class ImportIngredients extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-ingredients {file} {--parser=USDA} {--batchSize=50}';

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

    protected int $batchSize;
    protected array $nutrientMap = [];
    protected array $ingredientMap = [];
    protected array $categoryMap = [];
    protected $now;
    protected $baseDto;

    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (!Storage::disk('local')->exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $baseKey = $this->detectBaseKey($filePath);
        if (!$baseKey) {
            $this->error("Source file doesn't contain any of the allowed base keys.");
            return 1;
        }

        $this->info("Starting import from file: {$filePath}");
        $this->info("Detected base key: {$baseKey}");

        // Control the batch size
        $this->batchSize = (int) $this->option('batchSize');
        $batchSize = $this->batchSize;
        $count = 0;
        $batch = [];
        $batchIndex = 0;
        $this->categoryMap = IngredientCategory::pluck('id', 'name')->toArray();
        $this->nutrientMap = Nutrient::pluck('id', 'external_id')->toArray();
        $this->now = now();
        $this->baseDto = UsdaIngredientData::instance();

        foreach ($this->streamIngredients($filePath, $baseKey) as $sourceIngredient) {
            $dto = $this->convertToInternalStructure($sourceIngredient);
            // $batch[] = $dto->toArray();
            $batch[] = $dto;

            $count++;

            if (count($batch) >= $batchSize) {
                $batchIndex++;
                $start = $count - count($batch) + 1;
                $end = $count;

                $this->info("Processing batch #{$batchIndex}: ingredients {$start}-{$end}...");
                $this->processBatch($batch);
                $this->info("Finished batch #{$batchIndex} ({$end} total so far)");

                $batch = [];                
            }
        }

        // Process any leftover ingredients
        if (!empty($batch)) {
            $this->info("Processing final batch #{$batchIndex}: ingredients {$start}-{$end}...");
            $this->processBatch($batch);
            $this->info("Processed {$count} ingredients in total.");
        }

        $this->info("Import completed successfully.");
        return 0;
    }

    private function detectBaseKey(string $filePath): ?string
    {
        // read the first few kilobytes (enough to get the root key)
        $fullPath = Storage::disk('local')->path($filePath);

        $handle = fopen($fullPath, 'r');
        $chunk = fread($handle, 1024); // 1 KB
        fclose($handle);

        $handle = fopen($fullPath, 'r');
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

    /**
     * Stream ingredients from a JSON file using JsonMachine.
     *
     * @param string $filePath
     * @param string $baseKey
     * @return Generator
     */
    private function streamIngredients(string $filePath, string $baseKey): Generator
    {
        if (!Storage::disk('local')->exists($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        $this->info("Streaming JSON file: {$filePath}");
        $fullPath = Storage::disk('local')->path($filePath);
        $items = Items::fromFile($fullPath, [
            'pointer' => "/{$baseKey}",         // point to the correct root key in JSON
            'decoder' => new ExtJsonDecoder(true), // decode as associative arrays
        ]);

        foreach ($items as $item) {
            yield $item; // yield one ingredient at a time
        }
    }

    /**
     * Convert a raw source ingredient into the internal app structure.
     *
     * @param array|object $sourceIngredient
     * @return array
     */
    private function convertToInternalStructure($sourceIngredient): array
    {
        $sourceArray = is_array($sourceIngredient) ? $sourceIngredient : (array) $sourceIngredient;
        return $this->baseDto->load($sourceArray)->toArray();
    }

    protected function processBatch(array $batch): void
    {
        $now = $this->now;
        $categories = [];
        $ingredients = [];
        $nutrients = [];
        $nutrientsPivot = [];
        $nutritionFacts = [];

        // --- 1. Collect raw batch data ---
        foreach ($batch as $dto) {
            // Category
            $cat = $dto['category'];
            $categories[$cat['name']] = [
                'name' => $cat['name'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Ingredient
            $ing = $dto['ingredient'];
            $ingredients[$ing['external_id']] = array_merge($ing, [
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Nutrients
            foreach ($dto['nutrients'] as $nutrient) {
                $nutrients[$nutrient['external_id']] = array_merge($nutrient, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Pivots — keep only external IDs for now
            foreach ($dto['nutrients_pivot'] as $pivot) {
                $pivot['ingredient_external_id'] = $ing['external_id'];
                $pivot['nutrient_external_id'] = $pivot['nutrient_external_id'] ?? null; // ensure key exists
                $nutrientsPivot[] = $pivot;
            }

            // Nutrition facts
            foreach ($dto['nutrition_facts'] as $fact) {
                $fact['ingredient_external_id'] = $ing['external_id'];
                $nutritionFacts[] = $fact;
            }
        }

        // --- 2. Upsert categories ---
        $newCategories = array_filter($categories, fn($c) => !isset($this->categoryMap[$c['name']]));
        if (!empty($newCategories)) {
            IngredientCategory::upsert(array_values($newCategories), ['name'], ['updated_at']);
            $fetchedCategories = IngredientCategory::whereIn('name', array_keys($newCategories))->get();
            foreach ($fetchedCategories as $c) {
                $this->categoryMap[$c->name] = $c->id;
            }
        }

        // --- 3. Upsert nutrients ---
        foreach(array_chunk($nutrients, 50) as $chunk) {
            Nutrient::upsert(
                array_values($chunk),
                ['source', 'external_id'],
                ['name', 'description', 'derivation_code', 'derivation_description', 'updated_at']
            );
    
            $newNutrients = Nutrient::whereIn('external_id', array_keys($chunk))->get();
            foreach ($newNutrients as $n) {
                $this->nutrientMap[$n->external_id] = $n->id;
                // SyncNutrientToSearch::dispatch($n, 'upsert')->onQueue('nutrients');
            }
        }

        // --- 4. Upsert ingredients ---
        foreach(array_chunk($ingredients, 50) as $chunk) {
            Ingredient::upsert(
                array_values($chunk),
                ['source', 'external_id'],
                ['name', 'description', 'default_amount', 'default_amount_unit_id', 'updated_at']
            );

            $newIngredients = Ingredient::whereIn('external_id', array_keys($chunk))->get();
            foreach ($newIngredients as $i) {
                $this->ingredientMap[$i->external_id] = $i->id;
                // SyncIngredientToSearch::dispatch($i->loadForSearch(), 'upsert')->onQueue('ingredients');
            }
        }

        // --- 5. Resolve pivots using external IDs and maps ---
        $resolvedPivots = [];
        foreach ($nutrientsPivot as $pivot) {
            $ingredientId = $this->ingredientMap[$pivot['ingredient_external_id']] ?? null;
            $nutrientId  = $this->nutrientMap[$pivot['nutrient_external_id']] ?? null;

            if (!$ingredientId || !$nutrientId) {
                continue;
            }

            $resolvedPivots[] = [
                'ingredient_id' => $ingredientId,
                'nutrient_id' => $nutrientId,
                'amount' => $pivot['amount'],
                'amount_unit_id' => $pivot['amount_unit_id'],
                'portion_amount' => $pivot['portion_amount'] ?? null,
                'portion_amount_unit_id' => $pivot['portion_amount_unit_id'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // --- 6. Resolve nutrition facts ---
        $resolvedFacts = [];
        foreach ($nutritionFacts as $fact) {
            $ingredientId = $this->ingredientMap[$fact['ingredient_external_id']] ?? null;
            if (!$ingredientId) {
                continue;
            }

            $resolvedFacts[] = [
                'ingredient_id' => $ingredientId,
                'category' => $fact['category'],
                'name' => $fact['name'],
                'amount' => $fact['amount'],
                'amount_unit_id' => $fact['amount_unit_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // --- 7. Upsert pivot and facts ---
        if (!empty($resolvedPivots)) {
            foreach(array_chunk($resolvedPivots, 50) as $chunk) {
                DB::table('ingredient_nutrient')->upsert(
                    $chunk,
                    ['ingredient_id', 'nutrient_id', 'amount_unit_id'],
                    ['amount', 'portion_amount', 'portion_amount_unit_id', 'updated_at']
                );
            }
        }

        if (!empty($resolvedFacts)) {
            foreach(array_chunk($resolvedFacts, 50) as $chunk) {
                DB::table('ingredient_nutrition_facts')->upsert(
                    $resolvedFacts,
                    ['ingredient_id', 'category', 'name'],
                    ['amount', 'amount_unit_id', 'updated_at']
                );
            }
        }
    }

}
