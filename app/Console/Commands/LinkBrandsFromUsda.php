<?php

namespace App\Console\Commands;

use App\Console\Concerns\DumpsDatabase;
use App\Import\Pipeline\BrandLinker;
use App\Jobs\SyncSourceToSearch;
use App\Models\Source;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

class LinkBrandsFromUsda extends Command
{
    use DumpsDatabase;

    protected $signature = 'app:link-brands-from-usda
                            {file              : Absolute or storage-relative path to branded_food.json}
                            {--backup=         : Path where a pre-run database dump will be saved}
                            {--batchSize=500   : Number of records per processing batch}';

    protected $description = 'Link brands from a USDA branded_food.json file to existing ingredients';

    public function handle(BrandLinker $linker): int
    {
        $file      = $this->resolveFilePath($this->argument('file'));
        $backup    = $this->option('backup');
        $batchSize = (int) $this->option('batchSize');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $source = Source::where('slug', 'usda-food-data-central')->first();

        if (!$source) {
            $this->error('Source "usda-food-data-central" not found. Run the seeder first.');
            return self::FAILURE;
        }

        if ($backup) {
            $this->info("Creating database backup at: {$backup}");
            if (!$this->dumpDatabase($backup)) {
                $this->error('Database backup failed. Aborting.');
                return self::FAILURE;
            }
            $this->info('Backup created.');
        }

        try {
            $this->info("Starting brand linking (batchSize={$batchSize}): {$file}");

            $batch = [];
            $items = Items::fromFile($file, [
                'pointer' => '/BrandedFoods',
                'decoder' => new ExtJsonDecoder(true),
            ]);

            foreach ($items as $raw) {
                $batch[] = $raw;

                if (count($batch) >= $batchSize) {
                    $linker->process($batch, $source);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                $linker->process($batch, $source);
            }

            $this->info('Brand linking completed. Queuing Zinc re-index.');
            SyncSourceToSearch::dispatch($source)->onQueue('ingredients');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Brand linking failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function resolveFilePath(string $path): string
    {
        if (file_exists($path)) {
            return $path;
        }

        return Storage::disk('local')->path($path);
    }
}
