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

        $raw  = $this->llm->chat($messages, ['model' => config('ai.ollama.models.smart')]);
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

    public function planFetches(AgentContext $context): void
    {
        $searchResults = $context->getSearchResults();

        if (empty($searchResults)) {
            Log::debug('agent.planner.fetch_skip', ['reason' => 'no search results']);
            return;
        }

        $messages = [
            [
                'role'    => 'system',
                'content' => config('ai.planner.fetch_system_prompt'),
            ],
            [
                'role'    => 'user',
                'content' => "Question: {$context->getPrompt()}\n\nSearch results:\n" . json_encode($searchResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ],
        ];

        $raw  = $this->llm->chat($messages, ['model' => config('ai.ollama.models.smart')]);
        Log::debug('agent.planner.fetch_raw', ['raw' => $raw]);
        $json = $this->extractJson($raw);

        $decoded = json_decode($json, true);

        if (!is_array($decoded) || !array_is_list($decoded) || empty($decoded)) {
            $context->setFetchPlan([]);
            return;
        }

        foreach ($decoded as $step) {
            if (!isset($step['tool'], $step['args']['url']) || !is_string($step['tool']) || !is_string($step['args']['url'])) {
                $context->setFetchPlan([]);
                return;
            }
        }

        Log::debug('agent.planner.fetch_plan', ['steps' => count($decoded)]);
        $context->setFetchPlan($decoded);
    }

    private function extractJson(string $raw): string
    {
        $stripped = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $stripped = preg_replace('/\s*```$/', '', $stripped);

        return trim($stripped);
    }
}
