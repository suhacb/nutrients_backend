<?php

namespace App\Console\Commands;

use App\Console\Concerns\DumpsDatabase;
use App\Console\Concerns\RebuildsZincIndices;
use App\Import\Contracts\ImportSourceContract;
use App\Import\Pipeline\BatchPersistor;
use App\Import\Pipeline\ImportPipeline;
use App\Import\Sources\USDA\UsdaBrandTransformer;
use App\Import\Sources\USDA\UsdaImportSource;
use App\Import\Sources\USDA\UsdaIngredientTransformer;
use App\Import\Sources\USDA\UsdaNutrientTransformer;
use App\Import\Sources\USDA\UsdaNutritionFactTransformer;
use App\Import\Sources\USDA\UsdaPivotTransformer;
use App\Models\Unit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class ImportFromSource extends Command
{
    use DumpsDatabase, RebuildsZincIndices;

    protected $signature = 'app:import-from-source
                            {source            : Source name (supported: usda)}
                            {file              : Absolute or storage-relative path to the import file}
                            {--backup=         : Path where a pre-import database dump will be saved}
                            {--rebuild-zinc    : Drop and recreate all Zinc indices before importing}
                            {--batchSize=100   : Number of records per processing batch}';

    protected $description = 'Import nutritional data from a named source into the database';

    public function handle(BatchPersistor $persistor): int
    {
        $sourceName = $this->argument('source');
        $file       = $this->resolveFilePath($this->argument('file'));
        $backup     = $this->option('backup');
        $batchSize  = (int) $this->option('batchSize');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        try {
            $importSource = $this->resolveSource($sourceName);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($backup) {
            $this->info("Creating database backup at: {$backup}");
            if (!$this->dumpDatabase($backup)) {
                $this->error('Database backup failed. Aborting import.');
                return self::FAILURE;
            }
            $this->info('Backup created.');
        }

        if ($this->option('rebuild-zinc')) {
            $this->info('Rebuilding Zinc indices...');
            if (!$this->rebuildZincIndices()) {
                $this->error('Zinc index rebuild failed. Aborting import.');
                return self::FAILURE;
            }
            $this->info('Zinc indices rebuilt.');
            Artisan::call('app:queue-pending-sync', ['--model' => 'nutrients'], $this->output);
            $this->info('Seeded nutrients queued for sync.');
        }

        try {
            $pipeline = new ImportPipeline($importSource, $persistor, $batchSize);
            $this->info("Starting import (source={$sourceName}, batchSize={$batchSize}): {$file}");
            $pipeline->run($file);
            $this->info('Import completed successfully.');
            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error('Import failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function resolveSource(string $name): ImportSourceContract
    {
        return match (strtolower($name)) {
            'usda' => $this->makeUsdaSource(),
            default => throw new \InvalidArgumentException(
                "Unknown source: \"{$name}\". Supported sources: usda"
            ),
        };
    }

    private function makeUsdaSource(): UsdaImportSource
    {
        $unitMap = Unit::pluck('id', 'abbreviation')->all();

        return new UsdaImportSource(
            nutrientTransformer:      new UsdaNutrientTransformer($unitMap),
            ingredientTransformer:    new UsdaIngredientTransformer(),
            pivotTransformer:         new UsdaPivotTransformer($unitMap),
            nutritionFactTransformer: new UsdaNutritionFactTransformer($unitMap),
            brandTransformer:         new UsdaBrandTransformer(),
        );
    }

    private function resolveFilePath(string $path): string
    {
        if (file_exists($path)) {
            return $path;
        }

        return Storage::disk('local')->path($path);
    }
}
