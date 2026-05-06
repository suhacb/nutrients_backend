<?php

namespace App\AI\Agent;

use App\AI\Contracts\LlmClientContract;
use App\AI\ToolRegistry;
use Illuminate\Support\Facades\Log;

class Planner
{
    public function __construct(
        private readonly LlmClientContract $llm,
        private readonly ToolRegistry      $registry,
    ) {}

    public function plan(AgentContext $context): void
    {
        $capabilities = $this->registry->capabilities();
        $toolList     = json_encode($capabilities, JSON_PRETTY_PRINT);

        $messages = [
            [
                'role'    => 'system',
                'content' => config('ai.planner.system_prompt'),
            ],
            [
                'role'    => 'user',
                'content' => "Available tools:\n{$toolList}\n\nQuestion: {$context->getPrompt()}",
            ],
        ];

        $raw  = $this->llm->chat($messages);
        Log::debug('agent.planner.raw', ['prompt' => $context->getPrompt(), 'raw' => $raw]);
        $json = $this->extractJson($raw);
        Log::debug('agent.planner.json', ['json' => $json, 'decoded' => json_decode($json, true)]);

        $decoded = json_decode($json, true);

        if (!is_array($decoded) || empty($decoded) || !array_is_list($decoded)) {
            $context->setPlan([]);
            return;
        }

        foreach ($decoded as $step) {
            if (!isset($step['tool'], $step['args']) || !is_string($step['tool']) || !is_array($step['args'])) {
                $context->setPlan([]);
                return;
            }
        }

        $context->setPlan($decoded);
    }

    private function extractJson(string $raw): string
    {
        $stripped = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $stripped = preg_replace('/\s*```$/', '', $stripped);

        return trim($stripped);
    }
}
