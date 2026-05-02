<?php

namespace App\AI;

use App\AI\Agent\AgentContext;
use App\AI\Agent\Executor;
use App\AI\Agent\Planner;
use App\AI\Agent\Synthesizer;

class AgentOrchestrator
{
    public function __construct(
        private readonly Planner     $planner,
        private readonly Executor    $executor,
        private readonly Synthesizer $synthesizer,
    ) {}

    public function run(string $prompt): string
    {
        $context = new AgentContext($prompt);

        $this->planner->plan($context);
        $this->executor->execute($context);

        return $this->synthesizer->synthesize($context);
    }
}
