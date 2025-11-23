<?php

namespace App\Console\Commands;

use App\Models\Nutrient;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class ImportNutrients extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-nutrients {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import nutrient data from a JSON file, containing FDA Food Database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');
        $foods = $this->readDataFromFile($filePath);
        
        if (is_int($foods)) {
            return $foods;
        } elseif ($foods instanceof Collection) {
            $this->info("File $filePath read successfully.");
        } else {
            $this->info("Unexpected type: " . gettype($foods));
            return 0;
        }

        $nutrients = $this->extractFoodNutrients($foods);
        $this->import($nutrients);
        $this->info("Import completed: $filePath.");
        return 0;
    }

    private function readDataFromFile(string $filePath): Collection | int
    {
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }
        $this->info("Reading file: {$filePath}");
        $jsonContent = file_get_contents($filePath);
        $data = json_decode($jsonContent, true);
        unset($jsonContent);
        $extractedNutrients = collect();
        if ($data === null || !isset($data['FoundationFoods'])) {
            $this->error("Invalid JSON or missing 'FoundationFoods' key");
            return 1;
        }
        $foods = collect($data['FoundationFoods']);
        unset($data);
        
        return $foods;
    }

    private function extractFoodNutrients(Collection $foods): Collection
    {
        $extractedNutrients = collect();
        $foods->each(function($item, $key) use ($extractedNutrients) {
            $food = collect($item);
            $nutrients = collect($food->get('foodNutrients'));
        
            $nutrients->each(function($item, $key) use ($extractedNutrients) {
                $extractedNutrients->push([
                    'source' => 'USDA FoodData Central',
                    'external_id' => !empty($item['nutrient']['number']) ? $item['nutrient']['number'] : null,
                    'name' => $item['nutrient']['name'],
                    'description' => null,
                    'derivation_code' => !empty($item['nutrient']['derivation_code']) ? $item['nutrient']['derivation_code'] : null,
                    'derivation_description' => !empty($item['nutrient']['derivation_description']) ? $item['nutrient']['derivation_description'] : null,
                ]);

            });
        });
        return $extractedNutrients->unique()->sortBy('external_id', SORT_NATURAL)->values();
    }

    private function import(Collection $nutrients): void
    {
        $nutrients->each(function($item) {
            Nutrient::create([
                'source' => $item['source'],
                'external_id' => $item['external_id'],
                'name' => $item['name'],
                'description' => $item['description'],
                'derivation_code' => $item['derivation_code'],
                'derivation_description' => $item['derivation_description'],
            ]);
        });
    }
}