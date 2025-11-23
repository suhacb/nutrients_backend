<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;

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

        $foods->each(function($item, $key) use ($extractedNutrients) {
            $food = collect($item);
            $nutrients = collect($food->get('foodNutrients'));

            $nutrients->each(function($item, $key) use ($extractedNutrients) {
                $extractedNutrients->push([
                    'external_id' => $item['nutrient']['number'],
                    'name' => $item['nutrient']['name']
                ]);
            });
            $this->info('');
        });
        $extractedNutrients = $extractedNutrients->unique()->sortBy('external_id', SORT_NATURAL)->values();
        $extractedNutrients->each(function($item) {
            $this->info('external_id: ' . $item['external_id'] . ', name: ' . $item['name']);
        });

        $this->info("Done processing $filePath.");

        return 0;
    }
}
