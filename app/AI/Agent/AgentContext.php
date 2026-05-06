<?php

namespace App\AI\Agent;

class AgentContext
{
    private string $runId;
    private array  $plan        = [];
    private array  $toolResults = [];
    private array  $sources     = [];
    private array  $extractions = [];

    public function __construct(
        private readonly string $prompt,
    ) {
        $this->runId = uniqid('agent-', true);
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getRunId(): string
    {
        return $this->runId;
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

    public function addSource(string $path, string $url, string $title): void
    {
        $this->sources[] = ['path' => $path, 'url' => $url, 'title' => $title];
    }

    public function getSources(): array
    {
        return $this->sources;
    }

    public function addExtraction(string $path, int $sourceIndex): void
    {
        $this->extractions[] = ['path' => $path, 'sourceIndex' => $sourceIndex];
    }

    public function getExtractions(): array
    {
        return $this->extractions;
    }
}
