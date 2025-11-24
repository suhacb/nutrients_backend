<?php

namespace App\Jobs;

use App\Models\Nutrient;
use App\Services\Search\SearchServiceContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncNutrientToSearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Nutrient $nutrient;
    protected string $action;

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
        $index = 'nutrients';
        $payload = $this->nutrient->toArray();
        $id = $this->nutrient->id;

        switch ($this->action) {
            case 'insert':
                $search->insert($index, $payload);
                break;
            case 'update':
                $search->update($index, $id, $payload);
                break;
            case 'delete':
                $search->delete($index, $id);
                break;
        }
    }
}
