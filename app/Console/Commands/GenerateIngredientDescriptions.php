<?php

namespace App\Console\Commands;

use App\Jobs\GenerateIngredientDescription;
use App\Models\Ingredient;
use Illuminate\Console\Command;

class GenerateIngredientDescriptions extends Command
{
    protected $signature = 'ingredients:generate-descriptions
                            {--overwrite : Regenerate descriptions that already exist}
                            {--id=*      : Only process specific ingredient IDs}';

    protected $description = 'Dispatch jobs to generate AI descriptions for ingredients';

    public function handle(): int
    {
        $query = Ingredient::query()->withTrashed(false);

        $ids = array_filter(array_map('intval', $this->option('id')));

        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        } elseif (!$this->option('overwrite')) {
            $query->whereNull('description');
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('No ingredients to process.');
            return self::SUCCESS;
        }

        $this->info("Dispatching {$total} job(s) on the [ingredients] queue...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->select(['id', 'name'])->chunkById(200, function ($chunk) use ($bar) {
            foreach ($chunk as $ingredient) {
                GenerateIngredientDescription::dispatch($ingredient)->onQueue('ingredients');
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Done. Run `php artisan queue:listen --queue=ingredients` to process.');

        return self::SUCCESS;
    }
}
