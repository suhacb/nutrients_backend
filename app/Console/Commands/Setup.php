<?php

namespace App\Console\Commands;

use App\Console\Concerns\RebuildsZincIndices;
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

class Setup extends Command
{
    use RebuildsZincIndices;

    protected $signature = 'app:setup
                            {directory?        : Directory containing the USDA import files (defaults to storage/app/private)}
                            {--force           : Required when APP_ENV is not local}
                            {--batchSize=100   : Number of records per processing batch}';

    private const IMPORT_FILES = [
        'food_json.json',
        'sr_legacy_food.json',
        'branded_food.json',
        'surveyDownload.json',
    ];

    protected $description = 'Fresh install: migrate, seed, rebuild Zinc indices, and import USDA data. DESTRUCTIVE — local dev only.';

    public function handle(BatchPersistor $persistor): int
    {
        if (app()->environment() !== 'local' && !$this->option('force')) {
            $this->error('This command is destructive. Pass --force to run outside of local.');
            return self::FAILURE;
        }

        $directory = rtrim($this->argument('directory') ?? Storage::disk('local')->path(''), '/');

        $files = [];
        foreach (self::IMPORT_FILES as $filename) {
            $path = "{$directory}/{$filename}";
            if (!file_exists($path)) {
                $this->error("Import file not found: {$path}");
                return self::FAILURE;
            }
            $files[] = $path;
        }

        if (!$this->step('Refreshing database',     fn() => $this->refreshDatabase())) return self::FAILURE;
        if (!$this->step('Seeding database',        fn() => $this->seedDatabase()))    return self::FAILURE;
        if (!$this->step('Rebuilding Zinc indices', fn() => $this->rebuildZincIndices())) return self::FAILURE;
        if (!$this->step('Queuing seeded nutrients for sync', fn() => $this->queueSeededForSync())) return self::FAILURE;
        if (!$this->step('Importing USDA data',     fn() => $this->runImport($files, (int) $this->option('batchSize'), $persistor))) return self::FAILURE;

        $this->info('Setup complete.');
        return self::SUCCESS;
    }

    private function refreshDatabase(): bool
    {
        Artisan::call('migrate:fresh', [], $this->output);
        return true;
    }

    private function seedDatabase(): bool
    {
        Artisan::call('db:seed', [], $this->output);
        return true;
    }

    private function queueSeededForSync(): bool
    {
        Artisan::call('app:queue-pending-sync', ['--model' => 'nutrients'], $this->output);
        return true;
    }

    private function runImport(array $files, int $batchSize, BatchPersistor $persistor): bool
    {
        $unitMap = Unit::pluck('id', 'abbreviation')->all();

        $source = new UsdaImportSource(
            nutrientTransformer:      new UsdaNutrientTransformer($unitMap),
            ingredientTransformer:    new UsdaIngredientTransformer(),
            pivotTransformer:         new UsdaPivotTransformer($unitMap),
            nutritionFactTransformer: new UsdaNutritionFactTransformer($unitMap),
            brandTransformer:         new UsdaBrandTransformer(),
        );

        $pipeline = new ImportPipeline($source, $persistor, $batchSize);

        foreach ($files as $file) {
            $this->line('  Importing: ' . basename($file));
            $pipeline->run($file);
        }

        return true;
    }

    private function step(string $label, callable $fn): bool
    {
        $this->info("→ {$label}...");
        try {
            $result = $fn();
            if ($result) {
                $this->info("  ✓ Done.");
            }
            return $result;
        } catch (\Throwable $e) {
            $this->error("  Failed: {$e->getMessage()}");
            return false;
        }
    }
}
