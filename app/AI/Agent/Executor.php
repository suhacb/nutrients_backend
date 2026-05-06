<?php

namespace App\AI\Agent;

use App\AI\ToolRegistry;
use Illuminate\Support\Facades\Log;

class Executor
{
    public function __construct(
        private readonly ToolRegistry $registry,
    ) {}

    public function execute(AgentContext $context): void
    {
        foreach ($context->getPlan() as $i => $step) {
            Log::debug('agent.executor.tool_start', ['step' => $i + 1, 'tool' => $step['tool'], 'args' => $step['args']]);
            try {
                $tool   = $this->registry->resolve($step['tool']);
                $result = $tool->run($step['args']);
                $context->addToolResult($step['tool'], $step['args'], $result);
                Log::debug('agent.executor.tool_result', ['step' => $i + 1, 'tool' => $step['tool'], 'result' => $result]);
            } catch (\Throwable $e) {
                Log::debug('agent.executor.tool_error', ['step' => $i + 1, 'tool' => $step['tool'], 'error' => $e->getMessage()]);
                continue;
            }
        }
    }
}
