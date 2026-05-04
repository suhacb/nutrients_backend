<?php

namespace App\AI;

use App\AI\Agent\AgentContext;
use App\AI\Agent\Extractor;
use App\AI\Agent\Gatherer;
use App\AI\Agent\Planner;
use App\AI\Agent\Synthesizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AgentOrchestrator
{
    public function __construct(
        private readonly Planner     $planner,
        private readonly Gatherer    $gatherer,
        private readonly Extractor   $extractor,
        private readonly Synthesizer $synthesizer,
    ) {}

    public function run(string $prompt): string
    {
        $context = new AgentContext($prompt);

        try {
            $this->planner->plan($context);
            Log::debug('agent.plan', ['prompt' => $prompt, 'plan' => $context->getPlan()]);

            $this->gatherer->gather($context);
            $this->extractor->extract($context);

            return $this->synthesizer->synthesize($context);
        } finally {
            Storage::deleteDirectory("agent-runs/{$context->getRunId()}");
        }
    }
}
