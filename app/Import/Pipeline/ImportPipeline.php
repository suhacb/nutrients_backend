<?php

namespace App\Import\Pipeline;

use App\Import\Contracts\ImportSourceContract;
use App\Jobs\SyncSourceToSearch;
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
                $this->persistor->persist($batch, $source);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->persistor->persist($batch, $source);
        }

        SyncSourceToSearch::dispatch($source)->onQueue('ingredients');
    }
}
