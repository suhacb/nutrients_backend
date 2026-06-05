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

        SyncSourceToSearch::dispatch($source)->onQueue('ingredients');

        $newIds = $this->persistor->flushNewSourceNutrientIds();

        if (!empty($newIds)) {
            $this->dispatchEnrichmentChain($newIds);
        }
    }

    private function dispatchEnrichmentChain(array $sourceNutrientIds): void
    {
        $sourceNutrients = SourceNutrient::whereIn('id', $sourceNutrientIds)->get();
        $dedupJobs       = $sourceNutrients->map(fn ($sn) => new DeduplicateNutrient($sn))->all();

        Bus::batch($dedupJobs)
            ->onQueue('nutrients-dedup')
            ->then(function () use ($sourceNutrientIds) {
                // After dedup, collect the canonical nutrient IDs resolved from these source nutrients.
                $canonicalIds = SourceNutrient::whereIn('id', $sourceNutrientIds)
                    ->whereNotNull('nutrient_id')
                    ->pluck('nutrient_id')
                    ->unique()
                    ->all();

                if (empty($canonicalIds)) {
                    return;
                }

                $classifyJobs = Nutrient::whereIn('id', $canonicalIds)->get()
                    ->map(fn ($n) => new ClassifyNutrientParent($n))
                    ->all();

                Bus::batch($classifyJobs)
                    ->onQueue('nutrients-classify')
                    ->then(function () use ($canonicalIds) {
                        foreach (Nutrient::whereIn('id', $canonicalIds)->get() as $nutrient) {
                            GenerateNutrientDescription::dispatch($nutrient)->onQueue('nutrients');
                            SyncNutrientToSearch::dispatch($nutrient, 'insert')->onQueue('nutrients');
                        }
                    })
                    ->dispatch();
            })
            ->dispatch();
    }
}
