<?php

namespace App\Jobs;

use App\Enums\SyncStatus;
use App\Http\Resources\NutrientResource;
use App\Models\Nutrient;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use App\Services\Search\SearchServiceContract;

class SyncNutrientToSearch implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels;
    
    public Nutrient $nutrient;
    public string $action;

    /**
     * Create a new job instance.
     */
    public function __construct(Nutrient $nutrient, string $action)
    {
        $this->nutrient = $nutrient;
        $this->action = $action;
    }

    /**
     * Execute the job.
     */
    public function handle(SearchServiceContract $search): void
    {
        $index = config('zinc.indices.nutrients');
        $id    = $this->nutrient->id;

        switch ($this->action) {
            case 'insert':
            case 'update':
                $payload = (new NutrientResource($this->nutrient->loadForSearch()))->resolve();
                $this->action === 'insert'
                    ? $search->insert($index, $id, $payload)
                    : $search->update($index, $id, $payload);
                break;
            case 'delete':
                $search->delete($index, $id);
                break;
        }

        DB::table('nutrients')->where('id', $id)->update(['sync_status' => SyncStatus::Synced->value]);
    }

    public function failed(\Throwable $e): void
    {
        DB::table('nutrients')->where('id', $this->nutrient->id)->update(['sync_status' => SyncStatus::Failed->value]);
    }
}
