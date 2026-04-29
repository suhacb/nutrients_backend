<?php

namespace App\Console\Commands;

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
use Illuminate\Support\Facades\Storage;

class ImportFromSource extends Command
{
    protected $signature = 'app:import-from-source
                            {source            : Source name (supported: usda)}
                            {file              : Absolute or storage-relative path to the import file}
                            {--backup=         : Path where a pre-import database dump will be saved (required)}
                            {--batchSize=100   : Number of records per processing batch}';

    protected $description = 'Import nutritional data from a named source into the database';

    public function handle(BatchPersistor $persistor): int
    {
        $sourceName = $this->argument('source');
        $file       = $this->resolveFilePath($this->argument('file'));
        $backup     = $this->option('backup');
        $batchSize  = (int) $this->option('batchSize');

        if (!$backup) {
            $this->error('--backup is required. Provide a path for the pre-import database dump.');
            return self::FAILURE;
        }

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

        $this->info("Creating database backup at: {$backup}");
        if (!$this->dumpDatabase($backup)) {
            $this->error('Database backup failed. Aborting import.');
            return self::FAILURE;
        }
        $this->info('Backup created.');

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

    private function dumpDatabase(string $path): bool
    {
        try {
            $conn   = config('database.default');
            $config = config("database.connections.{$conn}");

            $host   = $config['host']     ?? '127.0.0.1';
            $port   = $config['port']     ?? 3306;
            $dbname = $config['database'] ?? '';
            $user   = $config['username'] ?? '';
            $pass   = $config['password'] ?? '';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $pdo = new \PDO($dsn, $user, $pass);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $sql    = "-- Backup: {$dbname} | " . now()->toIso8601String() . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
            $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                $row  = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_NUM);
                $sql .= "DROP TABLE IF EXISTS `{$table}`;\n{$row[1]};\n\n";

                $stmt = $pdo->query("SELECT * FROM `{$table}`");
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $cols   = implode(', ', array_map(fn($c) => "`{$c}`", array_keys($row)));
                    $values = implode(', ', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row));
                    $sql   .= "INSERT INTO `{$table}` ({$cols}) VALUES ({$values});\n";
                }

                $sql .= "\n";
            }

            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

            file_put_contents($path, $sql);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
