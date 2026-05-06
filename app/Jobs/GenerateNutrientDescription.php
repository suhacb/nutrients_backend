<?php

namespace App\Jobs;

use App\AI\AgentOrchestrator;
use App\Models\Nutrient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateNutrientDescription implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;

    public function __construct(
        public readonly Nutrient $nutrient,
    ) {}

    public function handle(AgentOrchestrator $orchestrator): void
    {
        Log::info('generate_description.start', ['nutrient_id' => $this->nutrient->id, 'name' => $this->nutrient->name]);

        $description = $orchestrator->run($this->buildPrompt($this->nutrient->name));

        $this->nutrient->update(['description' => $description]);

        Log::info('generate_description.done', ['nutrient_id' => $this->nutrient->id]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('generate_description.failed', [
            'nutrient_id' => $this->nutrient->id,
            'name'        => $this->nutrient->name,
            'error'       => $e->getMessage(),
        ]);
    }

    private function buildPrompt(string $name): string
    {
        return "You are a nutritional researcher. Please check trusted internet sources and write a description of {$name} from a nutritionist perspective. Cover: general description, role in the body, health benefits, recommended intake, supplementation, and dos and don'ts. Do not write disclaimers.";
    }
}
