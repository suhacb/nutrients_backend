<?php

namespace App\Import\Pipeline;

use App\Import\Contracts\ImportSourceContract;
use App\Jobs\ClassifyNutrientParent;
use App\Jobs\DeduplicateNutrient;
use App\Jobs\GenerateNutrientDescription;
use App\Jobs\SyncNutrientToSearch;
use App\Jobs\SyncSourceToSearch;
use App\Models\Nutrient;
use App\Models\SourceNutrient;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

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
                Log::warning('Skipping malformed import record', [
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if (count($batch) >= $this->batchSize) {
                $this->persistor->persist($batch, $source);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->persistor->persist($batch, $source);
        }

        // Index all ingredients for this source immediately (raw names — beautify separately).
        SyncSourceToSearch::dispatch($source)->onQueue('ingredients');

        $newIds = $this->persistor->flushNewSourceNutrientIds();

        if (!empty($newIds)) {
            $this->dispatchEnrichmentChain($newIds);
        }
    }

    private function dispatchEnrichmentChain(array $sourceNutrientIds): void
    {
        $dedupJobs = SourceNutrient::whereIn('id', $sourceNutrientIds)
            ->get()
            ->map(fn ($sn) => new DeduplicateNutrient($sn))
            ->all();

        Bus::batch($dedupJobs)
            ->onQueue('nutrients-dedup')
            ->then(function () use ($sourceNutrientIds) {
                // Collect canonical IDs resolved from these source nutrients.
                $canonicalIds = SourceNutrient::whereIn('id', $sourceNutrientIds)
                    ->whereNotNull('nutrient_id')
                    ->pluck('nutrient_id')
                    ->unique()
                    ->all();

                if (empty($canonicalIds)) {
                    return;
                }

                // Only classify canonicals that don't have a parent assigned yet.
                $classifyJobs = Nutrient::whereIn('id', $canonicalIds)
                    ->whereNull('parent_id')
                    ->get()
                    ->map(fn ($n) => new ClassifyNutrientParent($n))
                    ->all();

                // After classification (or immediately if nothing to classify), generate
                // descriptions for canonicals that don't have one, then sync them all.
                $dispatchDescriptionsAndSync = static function () use ($canonicalIds): void {
                    $descJobs = Nutrient::whereIn('id', $canonicalIds)
                        ->whereNull('description')
                        ->get()
                        ->map(fn ($n) => new GenerateNutrientDescription($n))
                        ->all();

                    $syncAll = static function () use ($canonicalIds): void {
                        Nutrient::whereIn('id', $canonicalIds)->each(
                            fn ($nutrient) => SyncNutrientToSearch::dispatch($nutrient, 'insert')->onQueue('nutrients')
                        );
                    };

                    if (empty($descJobs)) {
                        $syncAll();
                        return;
                    }

                    Bus::batch($descJobs)
                        ->onQueue('nutrients')
                        ->then($syncAll)
                        ->dispatch();
                };

                if (empty($classifyJobs)) {
                    $dispatchDescriptionsAndSync();
                    return;
                }

                Bus::batch($classifyJobs)
                    ->onQueue('nutrients-classify')
                    ->then($dispatchDescriptionsAndSync)
                    ->dispatch();
            })
            ->dispatch();
    }
}
