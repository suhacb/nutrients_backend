<?php

namespace App\AI\Agent;

class AgentContext
{
    private array $plan        = [];
    private array $toolResults = [];

    public function __construct(
        private readonly string $prompt,
    ) {}

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function setPlan(array $plan): void
    {
        $this->plan = $plan;
    }

    public function getPlan(): array
    {
        return $this->plan;
    }

    public function addToolResult(string $tool, array $args, mixed $result): void
    {
        $this->toolResults[] = [
            'tool'   => $tool,
            'args'   => $args,
            'result' => $result,
        ];
    }

    public function getToolResults(): array
    {
        return $this->toolResults;
    }
}
