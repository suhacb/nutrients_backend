<?php

namespace App\Console\Commands;

use Generator;
use App\Models\Unit;
use RuntimeException;
use JsonMachine\Items;
use App\Models\Nutrient;
use App\Models\Ingredient;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use App\Models\IngredientCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Database\Seeders\UnitsTableSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use App\Models\IngredientNutrientPivot;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Schema\Blueprint;
use App\Data\USDAFoodData\UsdaNutrientData;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use App\Models\IngredientIngredientCategory;
use App\Data\USDAFoodData\UsdaCategoriesData;
use App\Data\USDAFoodData\UsdaIngredientData;
use App\Data\USDAFoodData\UsdaNutrientConversionFactor;
use App\Data\USDAFoodData\UsdaIngredientNutrientPivotData;

class ImportIngredients2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-ingredients-2 {file} {--parser=USDA} {--batchSize=50}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import ingredients from source';


    protected $ingredientCount = 0;
    protected $nutrientCount = 0;
    protected $categoryCount = 0;
    protected UsdaIngredientData $ingredientDto;
    protected UsdaNutrientData $nutrientDto;
    protected UsdaCategoriesData $ingredientCategoryDto;
    protected UsdaIngredientNutrientPivotData $ingredientNutrientPivotData;
    protected UsdaNutrientConversionFactor $nutrientConversionFactorsDto;
    protected $now;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fileName = $this->argument('file');
        $this->checkIfFileExists($fileName); // If files does not exist, exception will be thrown
        $this->createStageDb();

        $this->info('Checking if base key is valid...');
        if (!$this->baseKeyIsValid($fileName)) {
            throw new RuntimeException('Source file doesn\'t contain any of the allowed base keys.');
        }
        $this->info('Base key is valid.');

        $this->now = now();
        $this->importUnits();
        $this->initDtos();
        $this->importToStage($fileName);
        $this->info('Imported ' . $this->ingredientCount . ' ingredients, ' . $this->nutrientCount . ' unique nutrients and ' . $this->categoryCount . ' unique categories.');
        $this->import();
        
        
        $batchSize = (int) $this->option('batchSize');
        return 0;
    }

    private function checkIfFileExists(string $fileName): bool
    {
        $fileExists = Storage::disk('local')->exists($fileName);
        if (!$fileExists) {
            throw new RuntimeException("File not found: {$fileName}");
        }
        return $fileExists;
    }

    private function createStageDb()
    {
        $this->info('Creating stage database...');

        Config::set('database.connections.sqlite_temp', [
            'driver' => 'sqlite',
            'database' => ':memory:', // use in-memory db (or use 'storage/app/temp/foo.sqlite')
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        $this->createIngredientsSchema();                   // Base table for ingredients
        $this->createNutrientsSchema();                     // Base table for nutrients
        $this->createIngredientNutrientSchema();            // Pivot table relating nutrients to ingredients with specified amounts
        $this->createNutrientConversionFactorsSchema();     // Table to relate conversion factors to ingredients
        $this->createIngredientCategoriesSchema();          // Base table for categories
        $this->createIngredientIngredientCategorySchema();  // Pivot table relating ingredients to categories
        $this->createFoodPortionsSchema();                  // Table relating food portions to ingredients
        $this->createUnitsSchema();                         // Base table for units
        $this->createLabelNutrientsSchema();                // Table relating ingeedient to label nutrient specification

        $this->info('Stage database created');
    }

    private function createIngredientsSchema(): void
    {
        Schema::connection('sqlite_temp')->create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('foodClass');
            $table->string('description')->unique();
            $table->unsignedInteger('ndbNumber')->nullable();
            $table->string('dataType');
            $table->unsignedInteger('fdcId');
        });

        $this->info('   ...ingredients table created...');
    }

    private function createNutrientsSchema(): void
    {
        Schema::connection('sqlite_temp')->create('nutrients', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('external_id')->nullable();
            $table->string('name')->unique();
        });

        $this->info('   ...nutrients table created...');
    }

    private function createIngredientNutrientSchema(): void
    {
        Schema::connection('sqlite_temp')->create('ingredient_nutrient', function (Blueprint $table) {
            $table->unsignedBigInteger('ingredient_id');
            $table->unsignedBigInteger('nutrient_id');
            $table->float('amount');
            $table->string('unit');
        });

        $this->info('   ...ingredient_nutrient table created...');
    }

    private function createNutrientConversionFactorsSchema(): void
    {
        Schema::connection('sqlite_temp')->create('nutrient_conversion_factors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ingredient_id');
            $table->string('conversion_factor_type');
            $table->string('conversion_factor_category');
            $table->float('value');
        });

        $this->info('   ...nutrient_conversion_factors table created...');
    }

    private function createIngredientCategoriesSchema(): void
    {
        Schema::connection('sqlite_temp')->create('ingredient_categories', function (Blueprint $table) {
            $table->id();
            $table->string('description')->unique();
        });

        $this->info('   ...ingredient_categories table created...');
    }

    private function createIngredientIngredientCategorySchema(): void
    {
        Schema::connection('sqlite_temp')->create('ingredient_ingredient_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('ingredient_id');
            $table->unsignedBigInteger('ingredient_category_id');
        });

        $this->info('   ...ingredient_ingredient_categories table created...');
    }

    private function createFoodPortionsSchema(): void
    {
        Schema::connection('sqlite_temp')->create('food_portions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ingredient_id');
            $table->unsignedInteger('value');
            $table->string('measure_unit_abbreviation');
            $table->float('gram_weight');
            $table->float('amount');
        });

        $this->info('   ...food_portions table created...');
    }

    private function createLabelNutrientsSchema(): void
    {
        Schema::connection('sqlite_temp')->create('ingredient_label_nutrients', function(Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ingredient_id');
            $table->string('label');
            $table->float('value');
            $table->float('serving_size');
            $table->string('serving_size_unit');
        });
    }

    private function createUnitsSchema(): void
    {
        Schema::connection('sqlite_temp')->create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('abbreviation');
            $table->string('type')->nullable();
            $table->timestamps();
        });

        $this->info('   ...units table created...');
    }

    private function baseKeyIsValid(string $fileName): bool
    {
        return !!$this->detectBaseKey($fileName);
    }

    private function detectBaseKey(string $fileName): ?string
    {
        // read the first few kilobytes (enough to get the root key)
        $fullPath = Storage::disk('local')->path($fileName);
        $handle = fopen($fullPath, 'r');
        $chunk = fread($handle, 1024); // 1 KB
        fclose($handle);

        $baseKeys = [
            'FoundationFoods',
            'SRLegacyFoods',
            'BrandedFoods'
        ];
        
        // Look for one of the known root keys immediately after a JSON object start
        foreach ($baseKeys as $key) {
            if (preg_match('/"\s*' . preg_quote($key, '/') . '\s*"\s*:/', $chunk)) {
                return $key;
            }
        }

        return null;
    }

    private function initDtos(): void
    {
        $this->ingredientDto = UsdaIngredientData::instance();
        $this->nutrientDto = UsdaNutrientData::instance();
        $this->ingredientCategoryDto = UsdaCategoriesData::instance();
        $this->ingredientNutrientPivotData = UsdaIngredientNutrientPivotData::instance();
        $this->nutrientConversionFactorsDto = UsdaNutrientConversionFactor::instance();
    }

    private function importUnits(): void
    {
        $this->info('Importing units to stage database...');
        $this->call('db:seed', [
            '--class' => UnitsTableSeeder::class,
            '--database' => 'sqlite_temp',
            '--quiet' => true
        ]);
        $this->info('Units imported.');
    }

    private function importToStage(string $fileName): void
    {
        $this->info('Importing data to stage database...');

        $filePath = Storage::disk('local')->path($fileName);
        $baseKey = $this->detectBaseKey($fileName);
        $count = 0;
        foreach ($this->streamIngredients($filePath, $baseKey) as $sourceIngredient) {
            $count++;
            // insert ingredient
            $ingredient_id = $this->insertIngredient($sourceIngredient);

            // insert nutrients
            $this->insertNutrients($sourceIngredient);
            $stagedNutrients = DB::connection('sqlite_temp')->table('nutrients')->get();

            // insert categories
            $category_id = $this->insertCategories($sourceIngredient);

            // insert ingredient_nutrient pivot
            $this->insertIngredientNutrientPivot($ingredient_id, $stagedNutrients, $sourceIngredient);

            // insert ingredient_ingredient_category pivot
            $this->insertIngredientIngredientCategoryPivot($ingredient_id, $category_id);

            // insert nutrient_conversion_factors
            $this->insertNutrientConversionFactors($ingredient_id, $sourceIngredient);

            // insert food_portions
            $this->insertFoodPortions($ingredient_id, $sourceIngredient);

            // insert labeled nutrients
            $this->insertLabelNutrients($ingredient_id, $sourceIngredient);

            if ($count % 1000 === 0) {
                $this->info("Processed {$count} ingredients into stage database...");
            }
        }
    }

    private function streamIngredients($filePath, $baseKey): Generator
    {
        $this->info("Streaming JSON file: {$filePath}");
        $items = Items::fromFile($filePath, [
            'pointer' => "/{$baseKey}",             // point to the correct root key in JSON
            'decoder' => new ExtJsonDecoder(true),  // decode as associative arrays
        ]);

        foreach ($items as $item) {
            yield $item; // yield one ingredient at a time
        }
    }

    private function insertIngredient(array $sourceIngredient): int
    {
        $itemToImport = $this->ingredientDto->load($sourceIngredient)->toStage();

        $existing = DB::connection('sqlite_temp')
            ->table('ingredients')
            ->where('description', $itemToImport['description'])
            ->first();

        if (!$existing) {
            $this->ingredientCount++;
            $id = DB::connection('sqlite_temp')
                ->table('ingredients')
                ->insertGetId($itemToImport);
        } else {
            $id = $existing->id;
        }

        return $id;
    }

    private function insertNutrients(array $sourceIngredient): void
    {
        $sourceNutrients = Arr::pluck($sourceIngredient['foodNutrients'], 'nutrient');

        // Keep only the first occurrence of each unique name
        $uniqueSourceNutrients = [];
        foreach ($sourceNutrients as $item) {
            if (!isset($uniqueSourceNutrients[$item['name']])) {
                $uniqueSourceNutrients[$item['name']] = $item;
            }
        }

        $existingRows = DB::connection('sqlite_temp')
            ->table('nutrients')
            ->whereIn('name', array_column($uniqueSourceNutrients, 'name'))
            ->get(['name', 'id'])
            ->keyBy('name'); // keyBy uniqueField for easy lookup
        
        $resultMap = []; // key => value mapping of description => id
    
        // Add existing items to result map
        foreach ($existingRows as $row) {
            $resultMap[$row->name] = $row->id;
        }

        // Filter out items that already exist
        $itemsToInsert = array_filter($uniqueSourceNutrients, function ($sourceNutrient) use ($resultMap) {
            return !isset($resultMap[$sourceNutrient['name']]);
        });

        // Insert in batches
        $chunks = array_chunk($itemsToInsert, 25);
        $nutrientsToInsert = [];
        foreach ($chunks as $chunk) {
            foreach ($chunk as $item) {
                $nutrientsToInsert[] = $this->nutrientDto->load($item)->toStage();
                $this->nutrientCount++;
            }
            DB::connection('sqlite_temp')->table('nutrients')->upsert($nutrientsToInsert, ['name'], ['external_id']);
        }
    }

    private function insertCategories(array $sourceIngredient): int | null
    {
        if (!array_key_exists('foodCategory', $sourceIngredient) && !array_key_exists('brandedFoodCategory', $sourceIngredient)) {
            return null;
        } else {
            if (array_key_exists('foodCategory', $sourceIngredient)) {
                $sourceCategory = $sourceIngredient['foodCategory'];
            } elseif (array_key_exists('brandedFoodCategory', $sourceIngredient)) {
                $sourceCategory['description'] = $sourceIngredient['brandedFoodCategory'];
            }
    
            $category = $this->ingredientCategoryDto->load($sourceCategory)->toStage();
            
            $existing = DB::connection('sqlite_temp')
                ->table('ingredient_categories')
                ->where('description', $category['description'])
                ->first();
    
            if (!$existing) {
                $this->categoryCount++;
                $id = DB::connection('sqlite_temp')
                    ->table('ingredient_categories')
                    ->insertGetId($category);
            } else {
                $id = $existing->id;
            }
    
            return $id;
        }
    }

    private function insertIngredientNutrientPivot(int $ingredient_id, Collection $stagedNutrients, array $sourceIngredient): void
    {
        $sourceNutrients = $sourceIngredient['foodNutrients'];
        

        // create array for nutrient_ingredient stage pivot
        $itemsToInsert = [];
        foreach($sourceNutrients as $sourceNutrient) {
            if (!isset($sourceNutrient['amount']) || $sourceNutrient['amount'] <= 0) {
                continue;
            }

            // Find nutrient ID in stage database
            $match = $stagedNutrients->firstWhere('name', $sourceNutrient['nutrient']['name']);
            if ($match) {
                $itemsToInsert[] = $this->ingredientNutrientPivotData->load([])->toStage([
                    'ingredient_id' => $ingredient_id,
                    'match' => $match,
                    'sourceNutrient' => $sourceNutrient
                ]);
            }
        }

        //insert into pivot array in stage by chunks of 30
        $chunks = array_chunk($itemsToInsert, 30);
        foreach ($chunks as $chunk) {
            DB::connection('sqlite_temp')->table('ingredient_nutrient')->insert($chunk);
        }
    }

    private function insertIngredientIngredientCategoryPivot(int $ingredient_id, int $category_id): void
    {
        DB::connection('sqlite_temp')->table('ingredient_ingredient_categories')->insert([
            'ingredient_id' => $ingredient_id,
            'ingredient_category_id' => $category_id
        ]);
    }

    private function insertNutrientConversionFactors(int $ingredient_id, array $sourceIngredient): void
    {
        if (array_key_exists('nutrientConversionFactors', $sourceIngredient)) {
            $nutrientConversionFactors = $sourceIngredient['nutrientConversionFactors'];
            $itemsToInsert = [];
    
            foreach ($nutrientConversionFactors as $factor) {
                $itemsToInsert[] = $this->nutrientConversionFactorsDto->load($factor)->toStage([
                    'ingredient_id' => $ingredient_id
                ]);
            }
    
            $chunks = array_chunk($itemsToInsert, 30);
            foreach($chunks as $chunk) {
                DB::connection('sqlite_temp')->table('nutrient_conversion_factors')->insert($chunk);
            }
        }
    }

    private function insertFoodPortions(int $ingredient_id, array $sourceIngredient): void
    {
        if (array_key_exists('foodPortions', $sourceIngredient)) {
            $foodPortions = $sourceIngredient['foodPortions'];
            $itemsToInsert = [];

            foreach ($foodPortions as $portion) {
                $itemsToInsert[] = [
                    'ingredient_id' => $ingredient_id,
                    'value' => $portion['value'],
                    'measure_unit_abbreviation' => $portion['measureUnit']['abbreviation'],
                    'gram_weight' => $portion['gramWeight'],
                    'amount' => $portion['amount'],
                ];
            }

            $chunks = array_chunk($itemsToInsert, 30);
            foreach($chunks as $chunk) {
                DB::connection('sqlite_temp')->table('food_portions')->insert($chunk);
            }
        }
    }

    private function insertLabelNutrients(int $ingredient_id, array $sourceIngredient): void
    {
        if (array_key_exists('labelNutrients', $sourceIngredient)) {
            $labelNutrients = $sourceIngredient['labelNutrients'];
            $itemsToInsert = [];

            foreach ($labelNutrients as $label => $info) {
                if (!isset($info['value']) || $info['value'] == 0) {
                    continue; // skip zero or missing values
                }

                $itemsToInsert[] = [
                    'ingredient_id' => $ingredient_id,
                    'label' => $label,
                    'value' => $info['value'],
                    'serving_size' => array_key_exists('servingSize', $info) ? $info['servingSize'] : 100,
                    'serving_size_unit' => array_key_exists('servingSizeUnit', $info) ? $info['servingSizeUnit'] : 'g'
                ];
            }

            DB::connection('sqlite_temp')->table('ingredient_label_nutrients')->insert($itemsToInsert);
        }
    }

    private function import(): void
    {
        $this->info('Transferring data from stage to final database...');

        // insert ingredient
        $this->importIngredients();

        // insert nutrients
        $this->importNutrients();

        // insert categories
        $this->importCategories();

        // insert ingredient_ingredient_category pivot
        $this->importIngredientIngredientCategories();

        // insert ingredient_nutrient pivot
        $this->importIngredientNutrient();

//        $this->insertIngredientIngredientCategoryPivot($ingredient_id, $category_id);
//
//        // insert nutrient_conversion_factors
//        $this->insertNutrientConversionFactors($ingredient_id, $sourceIngredient);
//
//        // insert food_portions
//        $this->insertFoodPortions($ingredient_id, $sourceIngredient);
//
//        // insert labeled nutrients
//        $this->insertLabelNutrients($ingredient_id, $sourceIngredient);
//
    }

    private function importIngredients(): void
    {
        $chunkSize = 100;
        $total = DB::connection('sqlite_temp')->table('ingredients')->count();
        $processed = 0;
        DB::connection('sqlite_temp')->table('ingredients')->orderBy('id')->chunk($chunkSize, function ($rows) use (&$processed, $total) {
            $rowsArray = $rows->toArray();
            $externalIds = array_filter(array_map(fn($r) => $r->ndbNumber ?? null, $rowsArray));

            $existingIds = [];
            if (!empty($externalIds)) {
                $existingIds = Ingredient::whereIn('external_id', $externalIds)
                    ->pluck('external_id')
                    ->all();
            }

            $newRows = array_filter($rowsArray, fn($r) => !in_array($r->ndbNumber, $existingIds));

            $ingredientsToInsert = [];

            foreach ($newRows as $row) {
                $ingredientData = $this->ingredientDto->load((array) $row)->toModel();

                // Add timestamps
                $ingredientData['created_at'] = $this->now;
                $ingredientData['updated_at'] = $this->now;

                $ingredientsToInsert[] = $ingredientData;
            }

            $insertedCount = 0;
            if (!empty($ingredientsToInsert)) {
                Ingredient::insertOrIgnore($ingredientsToInsert);
                $insertedCount = count($ingredientsToInsert);
            }

            $chunkCount = count($rowsArray);
            $skippedCount = $chunkCount - $insertedCount;
            $processed += $chunkCount;

            $this->info(sprintf(
                "Ingredients chunk processed: total=%d, inserted=%d, skipped=%d, overall processed=%d...",
                $chunkCount,
                $insertedCount,
                $skippedCount,
                $processed
            ));
        });
    }

    private function importNutrients(): void
    {
        $chunkSize = 100;
        $total = DB::connection('sqlite_temp')->table('nutrients')->count();
        $processed = 0;

        DB::connection('sqlite_temp')->table('nutrients')->orderBy('id')->chunk($chunkSize, function ($rows) use (&$processed, $total) {
            $rowsArray = $rows->toArray();
            $externalNames = array_filter(array_map(fn($r) => $r->name ?? null, $rowsArray));

            $existingNames = [];
            if (!empty($externalNames)) {
                $existingNames = Nutrient::whereIn('name', $externalNames)
                    ->pluck('name')
                    ->all();
            }

            $newRows = array_filter($rowsArray, fn($r) => !in_array($r->name, $existingNames));

            $nutrientsToInsert = [];

            foreach ($newRows as $row) {
                $nutrientData = $this->nutrientDto->load((array) $row)->toModel();

                // Add timestamps
                $nutrientData['created_at'] = $this->now;
                $nutrientData['updated_at'] = $this->now;

                $nutrientsToInsert[] = $nutrientData;
            }

            $insertedCount = 0;
            if (!empty($nutrientsToInsert)) {
                Nutrient::insertOrIgnore($nutrientsToInsert);
                $insertedCount = count($nutrientsToInsert);
            }

            $chunkCount = count($rowsArray);
            $skippedCount = $chunkCount - $insertedCount;
            $processed += $chunkCount;

            $this->info(sprintf(
                "Nutrients chunk processed: total=%d, inserted=%d, skipped=%d, overall processed=%d...",
                $chunkCount,
                $insertedCount,
                $skippedCount,
                $processed
            ));
        });
    }

    private function importCategories(): void
    {
        $chunkSize = 100;
        $total = DB::connection('sqlite_temp')->table('ingredient_categories')->count();
        $processed = 0;

        DB::connection('sqlite_temp')->table('ingredient_categories')->orderBy('id')->chunk($chunkSize, function ($rows) use (&$processed, $total) {
            $rowsArray = $rows->toArray();
            $externalNames = array_filter(array_map(fn($r) => $r->description ?? null, $rowsArray));

            $existingNames = [];
            if (!empty($externalNames)) {
                $existingNames = IngredientCategory::whereIn('name', $externalNames)
                    ->pluck('name')
                    ->all();
            }

            $newRows = array_filter($rowsArray, fn($r) => !in_array($r->description, $existingNames));

            $categoriesToInsert = [];

            foreach ($newRows as $row) {
                $categoryData = $this->ingredientCategoryDto->load((array) $row)->toModel();

                // Add timestamps
                $categoryData['created_at'] = $this->now;
                $categoryData['updated_at'] = $this->now;

                $categoriesToInsert[] = $categoryData;
            }

            $insertedCount = 0;
            if (!empty($categoriesToInsert)) {
                IngredientCategory::insertOrIgnore($categoriesToInsert);
                $insertedCount = count($categoriesToInsert);
            }

            $chunkCount = count($rowsArray);
            $skippedCount = $chunkCount - $insertedCount;
            $processed += $chunkCount;

            $this->info(sprintf(
                "Categories chunk processed: total=%d, inserted=%d, skipped=%d, overall processed=%d...",
                $chunkCount,
                $insertedCount,
                $skippedCount,
                $processed
            ));
        });
    }

    private function importIngredientIngredientCategories(): void
    {
        $chunkSize = 100;
        $stagedIngredients = DB::connection('sqlite_temp')->table('ingredients')->get();
        $itemsToInsert = [];

        foreach($stagedIngredients as $stagedIngredient) {
            $finalIngredient = Ingredient::where('external_id', $stagedIngredient->ndbNumber)->first();
    
            if (!$finalIngredient) {
                // Skip if final ingredient doesn't exist
                continue;
            }

            $stagedPivots = DB::connection('sqlite_temp')->table('ingredient_ingredient_categories')->where('ingredient_id', $stagedIngredient->id)->get();

            $stagedPivotIngredientCategoryIds = $stagedPivots->pluck('ingredient_category_id')->unique()->toArray();
            $stagedPivotIngredientCategories = DB::connection('sqlite_temp')->table('ingredient_categories')->whereIn('id', $stagedPivotIngredientCategoryIds)->get();
            $finalPivotIngredientCategories = IngredientCategory::whereIn('name', $stagedPivotIngredientCategories->pluck('description')->toArray())->get();

            $stagedPivots->each(function($stagedPivot) use ($stagedPivotIngredientCategories, $finalPivotIngredientCategories, $finalIngredient, &$itemsToInsert) {
                $stagedCategory = $stagedPivotIngredientCategories->firstWhere('id', $stagedPivot->ingredient_category_id);
                if (!$stagedCategory) {
                    return;
                }

                $finalCategory = $finalPivotIngredientCategories->firstWhere('name', $stagedCategory->description);
                if (!$finalCategory) {
                    return;
                }
                array_push($itemsToInsert, [
                    'ingredient_id' => $finalIngredient->id,
                    'ingredient_category_id' => $finalCategory->id
                ]);
            });

            if (count($itemsToInsert) > $chunkSize) {
                logger($itemsToInsert);
                IngredientIngredientCategory::insertOrIgnore($itemsToInsert);
                $itemsToInsert = [];
            }
        }

        IngredientIngredientCategory::insertOrIgnore($itemsToInsert);
    }


    public function importIngredientNutrient(): void
    {
        $chunkSize = 1000;
        $total = DB::connection('sqlite_temp')->table('ingredient_nutrient')->count();
        $units = Unit::get();
        $processed = 0;

        DB::connection('sqlite_temp')->table('ingredient_nutrient')
            ->orderBy('ingredient_id')
            ->orderBy('nutrient_id')
            ->chunk($chunkSize, function ($stagedPivotsChunk) use (&$processed, $total, $units) {
                // Extract ingredient & nutrient IDs from pivot chunk
                $ingredientIds = $stagedPivotsChunk->pluck('ingredient_id')->unique()->toArray();
                $nutrientIds   = $stagedPivotsChunk->pluck('nutrient_id')->unique()->toArray();

                // Fetch related stage ingredients & categories
                $stagedIngredients = DB::connection('sqlite_temp')->table('ingredients')->whereIn('id', $ingredientIds)->get();
                $stagedNutrients = DB::connection('sqlite_temp')->table('nutrients')->whereIn('id', $nutrientIds)->get();

                // Fetch corresponding final DB records (by external_id and name)
                $finalIngredients = Ingredient::whereIn('external_id', $stagedIngredients->pluck('ndbNumber')->toArray())->get();
                $finalNutrients  = Nutrient::whereIn('name', $stagedNutrients->pluck('name')->toArray())->get();

                // Build mapping array: stage ingredient_id → final ingredient model
                $ingredientMapToFinal = $stagedIngredients->mapWithKeys(function ($stageIngredient) use ($finalIngredients) {
                    $final = $finalIngredients->firstWhere('external_id', $stageIngredient->ndbNumber);
                    return [$stageIngredient->id => $final];
                })->filter(); // remove nulls

                $ingredientMapToStage = $finalIngredients->mapWithKeys(function ($finalIngredient) use ($stagedIngredients) {
                    $final = $stagedIngredients->firstWhere('ndbNumber', $finalIngredient->external_id);
                    return [$finalIngredient->id => $final];
                })->filter(); // remove nulls
                
                // Build mapping array: stage ingredient_category_id → final ingredient_category model
                $nutrientMapToFinal = $stagedNutrients->mapWithKeys(function ($stageNutrient) use ($finalNutrients) {
                    $final = $finalNutrients->firstWhere('name', $stageNutrient->name);
                    return [$stageNutrient->id => $final];
                })->filter(); // remove nulls

                // Build mapping array: stage ingredient_category_id → final ingredient_category model
                $nutrientMapToStage = $finalNutrients->mapWithKeys(function ($finalNutrient) use ($stagedNutrients) {
                    $final = $stagedNutrients->firstWhere('name', $finalNutrient->name);
                    return [$finalNutrient->id => $final];
                })->filter(); // remove nulls

                $finalPivotsChunk = IngredientNutrientPivot::whereIn('ingredient_id', $finalIngredients->pluck('id')->toArray())
                    ->whereIn('nutrient_id', $finalNutrients->pluck('id')->toArray())
                    ->get();
                
                $existingPairs = $finalPivotsChunk->mapWithKeys(function ($pivot) {
                    return [$pivot->ingredient_id . '_' . $pivot->nutrient_id => true];
                });

                $stagePivotsToProcess = $stagedPivotsChunk->filter(function ($row) use ($existingPairs) {
                    $key = $row->ingredient_id . '_' . $row->nutrient_id;
                    return !isset($existingPairs[$key]);
                });

                $itemsToInsert = $stagePivotsToProcess->map(function ($row) use ($ingredientMapToFinal, $nutrientMapToFinal, $units) {
                    $finalIngredient = $ingredientMapToFinal[$row->ingredient_id] ?? null;
                    $finalNutrient   = $nutrientMapToFinal[$row->nutrient_id] ?? null;

                    // Only include rows where both ingredient and category exist in final DB
                    if ($finalIngredient && $finalNutrient) {
                        return [
                            'ingredient_id' => $finalIngredient->id,
                            'nutrient_id' => $finalNutrient->id,
                            'amount' => $row->amount,
                            'amount_unit_id' => $units->firstWhere('abbreviation', $row->unit)->id,
                            'created_at' => $this->now,
                            'updated_at' => $this->now,
                        ];
                    }

                    // Skip rows that cannot be mapped
                    return null;
                })->filter()->values()->all();

                IngredientNutrientPivot::insertOrIgnore($itemsToInsert);
    
                $insertedCount = count($itemsToInsert);
                $chunkCount = count($stagedPivotsChunk);
                $skippedCount = $chunkCount - $insertedCount;
                $processed += $chunkCount;
    
                $this->info(sprintf(
                    "Ingredient-Nutrient pivot chunk processed: total=%d, inserted=%d, skipped=%d, overall processed=%d...",
                    $chunkCount,
                    $insertedCount,
                    $skippedCount,
                    $processed
                ));
            }
        );
    }
}
