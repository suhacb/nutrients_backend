<?php

namespace App\Import\Pipeline;

use App\Import\Contracts\ImportSourceContract;
use App\Jobs\SyncIngredientToSearch;
use App\Jobs\SyncNutrientToSearch;
use App\Models\Ingredient;
use App\Models\Nutrient;
use App\Models\Source;

class ImportPipeline {

    public function __construct(
        private readonly ImportSourceContract $source,
        private readonly BatchPersistor $persistor,
        private readonly int $batchSize = 100,
    ) {}

    public function run(string $file): void
    {
        $source = $this->source->getSource();
        $batch  = [];

        foreach ($this->source->stream($file) as $raw) {
            try {
                $batch[] = $this->source->transform($raw);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Skipping malformed import record', [
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if (count($batch) >= $this->batchSize) {
                $this->processBatch($batch, $source);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->processBatch($batch, $source);
        }
    }

    private function processBatch(array $batches, Source $source): void
    {
        $this->persistor->persist($batches, $source);
        $this->dispatchSyncJobs($batches);
    }

    private function dispatchSyncJobs(array $batches): void
    {
        $ingredientExternalIds = collect($batches)->pluck('ingredient.externalId')->unique()->all();
        $nutrientExternalIds   = collect($batches)->flatMap(fn($b) => collect($b->nutrients)->pluck('externalId'))->unique()->all();

        \App\Models\Ingredient::whereIn('external_id', $ingredientExternalIds)
            ->get()
            ->each(function (Ingredient $ingredient) {
                SyncIngredientToSearch::dispatch($ingredient->loadForSearch(), 'upsert')->onQueue('ingredients');
            });

        \App\Models\Nutrient::whereIn('external_id', $nutrientExternalIds)
            ->get()
            ->each(function (Nutrient $nutrient) {
                SyncNutrientToSearch::dispatch($nutrient, 'upsert')->onQueue('nutrients');
            });
    }
}