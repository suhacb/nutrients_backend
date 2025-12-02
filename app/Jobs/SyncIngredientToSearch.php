<?php
namespace App\Jobs;

use App\Models\Ingredient;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Search\SearchServiceContract;

class SyncIngredientToSearch implements ShouldQueue {
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels;
    
    public Ingredient $ingredient;
    public string $action;

    /**
     * Create a new job instance.
     */
    public function __construct(Ingredient $ingredient, string $action)
    {
        $this->ingredient = $ingredient;
        $this->action = $action;
    }

    /**
     * Execute the job.
     */
    public function handle(SearchServiceContract $search): void
    {
        $index = 'ingredients';
        $payload = $this->ingredient->toArray();
        $id = $this->ingredient->id;

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