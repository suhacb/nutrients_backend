<?php
namespace App\Jobs;

use App\Models\Ingredient;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Search\SearchServiceContract;
use stdClass;

class SyncIngredientToSearch implements ShouldQueue {
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels;
    
    public int $id;
    public ?Ingredient $ingredient;
    public string $action;

    /**
     * Create a new job instance.
     */
    public function __construct(Ingredient|stdClass $ingredient, string $action)
    {
        if ($ingredient instanceof Ingredient) {
            $this->ingredient = $ingredient;
            $this->id = $ingredient->id;
        } else {
            // For force deletes or dummy objects, only store the id
            $this->ingredient = null;
            $this->id = $ingredient->id;
        }
        $this->action = $action;
    }

    /**
     * Execute the job.
     */
    public function handle(SearchServiceContract $search): void
    {
        $index = 'ingredients';

        switch ($this->action) {
            case 'insert':
            case 'update':
                // Try the model instance first, fallback to DB query
                $ingredient = $this->ingredient ?? Ingredient::find($this->id);
                if ($ingredient) {
                    $payload = $ingredient->toArray();
                    $this->action === 'insert'
                        ? $search->insert($index, $payload)
                        : $search->update($index, $this->id, $payload);
                }
                break;

            case 'delete':
                // Only use the id; no need for the model
                $search->delete($index, $this->id);
                break;
        }
    }
}