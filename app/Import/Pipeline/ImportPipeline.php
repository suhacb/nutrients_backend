<?php

namespace App\Import\Pipeline;

use App\Import\Contracts\ImportSourceContract;
use App\Jobs\ClassifyNutrientParent;
use App\Jobs\DeduplicateNutrient;
use App\Jobs\GenerateNutrientDescription;
use App\Jobs\SyncNutrientToSearch;
use App\Jobs\SyncSourceToSearch;
use App\Models\Nutrient;
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

        $newIds = $this->persistor->flushNewNutrientIds();

        if (!empty($newIds)) {
            $this->dispatchEnrichmentChain($newIds);
        }
    }

    private function dispatchEnrichmentChain(array $nutrientIds): void
    {
        $nutrients    = Nutrient::whereIn('id', $nutrientIds)->get();
        $dedupJobs    = $nutrients->map(fn ($n) => new DeduplicateNutrient($n))->all();
        $classifyJobs = $nutrients->map(fn ($n) => new ClassifyNutrientParent($n))->all();

        Bus::batch($dedupJobs)
            ->onQueue('nutrients-dedup')
            ->then(function () use ($classifyJobs, $nutrientIds) {
                Bus::batch($classifyJobs)
                    ->onQueue('nutrients-classify')
                    ->then(function () use ($nutrientIds) {
                        foreach (Nutrient::whereIn('id', $nutrientIds)->get() as $nutrient) {
                            GenerateNutrientDescription::dispatch($nutrient)->onQueue('nutrients');
                            SyncNutrientToSearch::dispatch($nutrient, 'insert')->onQueue('nutrients');
                        }
                    })
                    ->dispatch();
            })
            ->dispatch();
    }
}
