<?php

namespace App\Jobs;

use App\AI\AgentOrchestrator;
use App\Models\Ingredient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateIngredientDescription implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;

    public function __construct(
        public readonly Ingredient $ingredient,
    ) {}

    public function handle(AgentOrchestrator $orchestrator): void
    {
        Log::info('generate_ingredient_description.start', ['ingredient_id' => $this->ingredient->id, 'name' => $this->ingredient->name]);

        $this->ingredient->loadMissing('brand');
        $description = $orchestrator->run($this->buildPrompt($this->ingredient->name, $this->ingredient->brand?->name));

        $this->ingredient->update(['description' => $description]);

        Log::info('generate_ingredient_description.done', ['ingredient_id' => $this->ingredient->id]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('generate_ingredient_description.failed', [
            'ingredient_id' => $this->ingredient->id,
            'name'          => $this->ingredient->name,
            'error'         => $e->getMessage(),
        ]);
    }

    private function buildPrompt(string $name, ?string $brand): string
    {
        $subject    = $brand ? "{$name} (brand: {$brand})" : $name;
        $categories = implode(', ', config('ai.ingredient_description.categories', []));
        $diets      = implode(', ', config('ai.ingredient_description.diets', []));

        return "You are a nutritional researcher. Please check trusted internet sources and write a description of {$subject} as a food ingredient. Cover: {$categories}. For diet compatibility, discuss the ingredient's suitability for the following diets: {$diets}. Do not write disclaimers.";
    }
}
