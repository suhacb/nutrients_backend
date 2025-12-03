<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use App\Parsers\ParserContract;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use App\Parsers\USDA\UsdaIngredientsParser;

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
        $parserOption = $this->option('parser');
        $foods = $this->readDataFromFile($filePath);

        $parser = $this->resolveParser($parserOption);
        if (!$parser instanceof ParserContract) {
            $this->error("Invalid parser specified: {$parserOption}");
            return 1;
        }
        
        if (is_int($foods)) {
            return $foods;
        }

        $ingredients = $parser->parse($foods);
        $this->import($ingredients);
        $this->info("Import completed: $filePath.");
        return 0;
    }

    private function resolveParser(string $parserOption): ?ParserContract
    {
        $parsers = [
            'USDA' => UsdaIngredientsParser::class,
            // Future parsers can be added here:
            // 'OtherParser' => OtherParser::class,
        ];

        if (!isset($parsers[$parserOption])) {
            return null;
        }

        return app($parsers[$parserOption]);
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
        
        return $data;
    }

    private function import(Collection $ingredients): void
    {
        $ingredients->each(function ($item) {
            $ingredient = Ingredient::firstOrCreate([
                'source' => $item['source'],
                'name' => $item['name'],
            ],
            $item->toArray()
            );
            collect($item->nutrients)->each(function($nutrient) use ($ingredient) {
                $ingredient->nutrients->attach($nutrient);
            });
        });
    }
}
