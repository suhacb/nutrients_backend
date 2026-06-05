<?php

namespace App\Console\Commands;

use App\Enums\SyncStatus;
use App\Jobs\SyncIngredientToSearch;
use App\Jobs\SyncNutrientToSearch;
use App\Models\Ingredient;
use App\Models\Nutrient;
use Illuminate\Console\Command;

class QueuePendingSync extends Command
{
    protected $signature = 'app:queue-pending-sync
                            {--model=           : Limit to "ingredients" or "nutrients" (default: both)}
                            {--include-failed   : Also queue records with sync_status=failed}';

    protected $description = 'Dispatch sync jobs for all pending (and optionally failed) ingredients and nutrients';

    public function handle(): int
    {
        $model          = $this->option('model');
        $includeFailed  = $this->option('include-failed');

        if ($model !== null && !in_array($model, ['ingredients', 'nutrients'], true)) {
            $this->error("Invalid --model value \"{$model}\". Accepted: ingredients, nutrients.");
            return self::FAILURE;
        }

        $statuses = $includeFailed
            ? [SyncStatus::Pending->value, SyncStatus::Failed->value]
            : [SyncStatus::Pending->value];

        $total = 0;

        if ($model === null || $model === 'ingredients') {
            $count = $this->queueIngredients($statuses);
            if ($count > 0) {
                $this->info("Queued {$count} ingredient(s) for sync.");
            }
            $total += $count;
        }

        if ($model === null || $model === 'nutrients') {
            $count = $this->queueNutrients($statuses);
            if ($count > 0) {
                $this->info("Queued {$count} nutrient(s) for sync.");
            }
            $total += $count;
        }

        if ($total === 0) {
            $this->info('No pending records found.');
        }

        return self::SUCCESS;
    }

    private function queueIngredients(array $statuses): int
    {
        $count = 0;
        Ingredient::where(fn ($q) => $q->whereIn('sync_status', $statuses)->orWhereNull('sync_status'))
            ->each(function (Ingredient $ingredient) use (&$count) {
                SyncIngredientToSearch::dispatch($ingredient, 'insert')->onQueue('ingredients');
                $count++;
            });
        return $count;
    }

    private function queueNutrients(array $statuses): int
    {
        $count = 0;
        Nutrient::where(fn ($q) => $q->whereIn('sync_status', $statuses)->orWhereNull('sync_status'))
            ->each(function (Nutrient $nutrient) use (&$count) {
                SyncNutrientToSearch::dispatch($nutrient, 'insert')->onQueue('nutrients');
                $count++;
            });
        return $count;
    }
}
