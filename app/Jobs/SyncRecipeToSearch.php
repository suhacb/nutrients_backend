<?php

namespace App\Jobs;

use App\Enums\SyncStatus;
use App\Models\Recipe;
use App\Services\Search\SearchServiceContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use stdClass;

class SyncRecipeToSearch implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels;

    public int $id;
    public ?Recipe $recipe;
    public string $action;

    public function __construct(Recipe|stdClass $recipe, string $action)
    {
        if ($recipe instanceof Recipe) {
            $this->recipe = $recipe;
            $this->id     = $recipe->id;
        } else {
            $this->recipe = null;
            $this->id     = $recipe->id;
        }
        $this->action = $action;
    }

    public function handle(SearchServiceContract $search): void
    {
        $index = config('zinc.indices.recipes');

        switch ($this->action) {
            case 'insert':
            case 'update':
                $recipe = $this->recipe ?? Recipe::find($this->id);
                if ($recipe) {
                    $payload = array_merge(
                        $recipe->loadForSearch()->toArray(),
                        ['nutrient_profile' => $recipe->computeNutrientProfile()]
                    );
                    $this->action === 'insert'
                        ? $search->insert($index, $this->id, $payload)
                        : $search->update($index, $this->id, $payload);
                }
                break;

            case 'delete':
                $search->delete($index, $this->id);
                break;
        }

        DB::table('recipes')->where('id', $this->id)->update(['sync_status' => SyncStatus::Synced->value]);
    }

    public function failed(\Throwable $e): void
    {
        DB::table('recipes')->where('id', $this->id)->update(['sync_status' => SyncStatus::Failed->value]);
    }
}
