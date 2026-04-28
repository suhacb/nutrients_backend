<?php

namespace App\Console\Commands;

use App\Import\Pipeline\BatchPersistor;
use App\Import\Pipeline\ImportPipeline;
use App\Import\Sources\USDA\UsdaImportSource;
use App\Import\Sources\USDA\UsdaIngredientTransformer;
use App\Import\Sources\USDA\UsdaNutrientTransformer;
use App\Import\Sources\USDA\UsdaNutritionFactTransformer;
use App\Import\Sources\USDA\UsdaPivotTransformer;
use App\Models\Unit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class Setup extends Command
{
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

    private string $zincBaseUri;
    private string $zincUser;
    private string $zincPassword;

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

        $this->zincBaseUri  = config('zinc.base_url');
        $this->zincUser     = config('zinc.username');
        $this->zincPassword = config('zinc.password');

        if (!$this->step('Refreshing database',    fn() => $this->refreshDatabase())) return self::FAILURE;
        if (!$this->step('Seeding database',       fn() => $this->seedDatabase()))    return self::FAILURE;
        if (!$this->step('Rebuilding Zinc indices', fn() => $this->rebuildZincIndices())) return self::FAILURE;
        if (!$this->step('Importing USDA data',    fn() => $this->runImport($files, (int) $this->option('batchSize'), $persistor))) return self::FAILURE;

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

    private function rebuildZincIndices(): bool
    {
        $indices = ['ingredients', 'nutrients'];

        foreach ($indices as $index) {
            $this->line("  Deleting index: {$index}");
            $response = Http::withBasicAuth($this->zincUser, $this->zincPassword)
                ->delete("{$this->zincBaseUri}/api/index/{$index}");

            if (!$response->successful() && $response->status() !== 404) {
                $this->error("Failed to delete Zinc index '{$index}': {$response->body()}");
                return false;
            }

            $this->line("  Creating index: {$index}");
            $response = Http::withBasicAuth($this->zincUser, $this->zincPassword)
                ->put("{$this->zincBaseUri}/api/index", $this->indexPayload($index));

            if (!$response->successful()) {
                $this->error("Failed to create Zinc index '{$index}': {$response->body()}");
                return false;
            }
        }

        return true;
    }

    private function indexPayload(string $index): array
    {
        return match ($index) {
            'ingredients' => [
                'name'         => 'ingredients',
                'storage_type' => 'disk',
                'shards'       => 1,
                'replicas'     => 0,
                'fields'       => [
                    'id'                     => ['type' => 'integer'],
                    'external_id'            => ['type' => 'keyword'],
                    'source'                 => ['type' => 'keyword'],
                    'class'                  => ['type' => 'keyword'],
                    'name'                   => ['type' => 'text'],
                    'description'            => ['type' => 'text'],
                    'slug'                   => ['type' => 'keyword'],
                    'default_amount'         => ['type' => 'numeric'],
                    'default_amount_unit_id' => ['type' => 'integer'],
                    'created_at'             => ['type' => 'date'],
                    'updated_at'             => ['type' => 'date'],
                    'deleted_at'             => ['type' => 'date', 'index' => false],
                ],
            ],
            'nutrients' => [
                'name'         => 'nutrients',
                'storage_type' => 'disk',
                'shards'       => 1,
                'replicas'     => 0,
                'fields'       => [
                    'id'                     => ['type' => 'integer'],
                    'source_id'              => ['type' => 'integer'],
                    'external_id'            => ['type' => 'keyword'],
                    'name'                   => ['type' => 'text'],
                    'description'            => ['type' => 'text'],
                    'parent_id'              => ['type' => 'integer'],
                    'slug'                   => ['type' => 'keyword'],
                    'canonical_unit_id'      => ['type' => 'integer'],
                    'iu_to_canonical_factor' => ['type' => 'numeric'],
                    'is_label_standard'      => ['type' => 'boolean'],
                    'display_order'          => ['type' => 'integer'],
                    'created_at'             => ['type' => 'date'],
                    'updated_at'             => ['type' => 'date'],
                    'deleted_at'             => ['type' => 'date', 'index' => false],
                ],
            ],
            default => throw new \InvalidArgumentException("Unknown index: {$index}"),
        };
    }

    private function runImport(array $files, int $batchSize, BatchPersistor $persistor): bool
    {
        $unitMap = Unit::pluck('id', 'abbreviation')->all();

        $source = new UsdaImportSource(
            nutrientTransformer:      new UsdaNutrientTransformer($unitMap),
            ingredientTransformer:    new UsdaIngredientTransformer(),
            pivotTransformer:         new UsdaPivotTransformer($unitMap),
            nutritionFactTransformer: new UsdaNutritionFactTransformer($unitMap),
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
