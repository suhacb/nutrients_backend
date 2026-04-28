<?php

namespace App\Import\Sources\USDA;

use App\Import\Records\ImportBatch;
use App\Import\Records\IngredientCategoryRecord;
use App\Models\Source;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

class UsdaImportSource implements \App\Import\Contracts\ImportSourceContract {

    private const ROOT_KEYS = [
        'FoundationFoods',
        'SRLegacyFoods',
        'BrandedFoods',
        'SurveyFoods',
    ];

    public function __construct(
        private readonly UsdaNutrientTransformer      $nutrientTransformer,
        private readonly UsdaIngredientTransformer    $ingredientTransformer,
        private readonly UsdaPivotTransformer         $pivotTransformer,
        private readonly UsdaNutritionFactTransformer $nutritionFactTransformer,
    ) {}

    public function getSource(): Source
    {
        $source = Source::where('slug', 'usda-food-data-central')->first();

        if (!$source) {
            throw new \RuntimeException('Source "usda-food-data-central" not found. Run the seeder first.');
        }

        return $source;
    }

    public function stream(string $file): \Generator
    {
        if (!file_exists($file)) {
            throw new \RuntimeException("File not found: {$file}");
        }

        $rootKey = $this->detectRootKey($file);

        $items = \JsonMachine\Items::fromFile($file, [
            'pointer' => "/{$rootKey}",
            'decoder' => new ExtJsonDecoder(true),
        ]);

        foreach ($items as $item) {
            yield $item;
        }
    }

    public function transform(array $raw): ImportBatch
    {
        $ingredientExternalId = strval($raw['fdcId']);

        $nutrients = [];
        $pivots    = [];

        foreach ($raw['foodNutrients'] as $foodNutrient) {
            $nutrients[] = $this->nutrientTransformer->transform($foodNutrient['nutrient']);
            $pivots[]    = $this->pivotTransformer->transform($foodNutrient, $ingredientExternalId);
        }

        $nutritionFacts = !empty($raw['labelNutrients'])
            ? $this->nutritionFactTransformer->transform($raw['labelNutrients'], $ingredientExternalId)
            : [];

        return new ImportBatch(
            ingredient:          $this->ingredientTransformer->transform($raw),
            category:            $this->extractCategory($raw),
            nutrients:           $nutrients,
            ingredientNutrients: $pivots,
            nutritionFacts:      $nutritionFacts,
        );
    }

    private function extractCategory(array $raw): IngredientCategoryRecord
    {
        $name = $raw['foodCategory']['description']
            ?? $raw['brandedFoodCategory']
            ?? 'Uncategorized';

        return new IngredientCategoryRecord($name);
    }

    private function detectRootKey(string $file): string
    {
        $handle = fopen($file, 'r');
        $chunk  = fread($handle, 16384);
        fclose($handle);

        foreach (self::ROOT_KEYS as $key) {
            if (preg_match('/"' . preg_quote($key, '/') . '"\s*:/', $chunk)) {
                return $key;
            }
        }

        throw new \RuntimeException("No recognised root key found in file: {$file}");
    }
}