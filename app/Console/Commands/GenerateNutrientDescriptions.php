<?php

namespace App\Console\Commands;

use App\Jobs\GenerateNutrientDescription;
use App\Models\Nutrient;
use Illuminate\Console\Command;

class GenerateNutrientDescriptions extends Command
{
    protected $signature = 'nutrients:generate-descriptions
                            {--overwrite : Regenerate descriptions that already exist}
                            {--id=* : Only process specific nutrient IDs}';

    protected $description = 'Dispatch jobs to generate AI descriptions for nutrients';

    public function handle(): int
    {
        $query = Nutrient::query()->withTrashed(false);

        $ids = array_filter(array_map('intval', $this->option('id')));

        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        } elseif (!$this->option('overwrite')) {
            $query->whereNull('description');
        }

        $nutrients = $query->get(['id', 'name']);

        if ($nutrients->isEmpty()) {
            $this->info('No nutrients to process.');
            return self::SUCCESS;
        }

        $this->info("Dispatching {$nutrients->count()} job(s) on the [nutrients] queue...");

        $bar = $this->output->createProgressBar($nutrients->count());
        $bar->start();

        foreach ($nutrients as $nutrient) {
            GenerateNutrientDescription::dispatch($nutrient)->onQueue('nutrients');
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done. Run `php artisan queue:listen --queue=nutrients` to process.');

        return self::SUCCESS;
    }
}
