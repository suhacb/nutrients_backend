<?php

namespace App\AI\Agent;

use App\AI\ToolRegistry;

class Executor
{
    public function __construct(
        private readonly ToolRegistry $registry,
    ) {}

    public function execute(AgentContext $context): void
    {
        foreach ($context->getPlan() as $step) {
            try {
                $tool   = $this->registry->resolve($step['tool']);
                $result = $tool->run($step['args']);
                $context->addToolResult($step['tool'], $step['args'], $result);
            } catch (\Throwable) {
                continue;
            }
        }
    }
}
