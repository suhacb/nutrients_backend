<?php

namespace App\Console\Commands;

use App\Jobs\GenerateIngredientDescription;
use App\Jobs\GenerateNutrientDescription;
use App\Models\Ingredient;
use App\Models\Nutrient;
use Illuminate\Console\Command;

class GenerateDescriptions extends Command
{
    protected $signature = 'app:generate-descriptions
                            {model         : "ingredients" or "nutrients"}
                            {--overwrite   : Regenerate descriptions that already exist}
                            {--id=*        : Only process specific IDs}';

    protected $description = 'Dispatch jobs to generate AI descriptions for ingredients or nutrients';

    private const CONFIG = [
        'ingredients' => [
            'modelClass' => Ingredient::class,
            'jobClass'   => GenerateIngredientDescription::class,
            'queue'      => 'ingredients',
            'label'      => 'ingredient',
        ],
        'nutrients' => [
            'modelClass' => Nutrient::class,
            'jobClass'   => GenerateNutrientDescription::class,
            'queue'      => 'nutrients',
            'label'      => 'nutrient',
        ],
    ];

    public function handle(): int
    {
        $modelKey = $this->argument('model');

        if (!isset(self::CONFIG[$modelKey])) {
            $this->error("Unknown model \"{$modelKey}\". Accepted: ingredients, nutrients.");
            return self::FAILURE;
        }

        ['modelClass' => $modelClass, 'jobClass' => $jobClass, 'queue' => $queue, 'label' => $label] = self::CONFIG[$modelKey];

        $query = $modelClass::query()->withTrashed(false);

        $ids = array_filter(array_map('intval', $this->option('id')));

        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        } elseif (!$this->option('overwrite')) {
            $query->whereNull('description');
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info("No {$label}s to process.");
            return self::SUCCESS;
        }

        $this->info("Dispatching {$total} job(s) on the [{$queue}] queue...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->select(['id', 'name'])->chunkById(200, function ($chunk) use ($jobClass, $queue, $bar) {
            foreach ($chunk as $record) {
                $jobClass::dispatch($record)->onQueue($queue);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done. Run `php artisan queue:listen --queue={$queue}` to process.");

        return self::SUCCESS;
    }
}
