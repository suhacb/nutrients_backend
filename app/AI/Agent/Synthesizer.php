<?php

namespace App\AI\Agent;

use App\AI\Contracts\LlmClientContract;

class Synthesizer
{
    public function __construct(
        private readonly LlmClientContract $llm,
    ) {}

    public function synthesize(AgentContext $context): string
    {
        $userMessage = "Question: {$context->getPrompt()}";

        $toolResults = $context->getToolResults();

        if (!empty($toolResults)) {
            $userMessage .= "\n\nResearch results:\n" . $this->formatResults($toolResults);
            $userMessage .= "\n\nBased on the research above, provide a comprehensive and accurate answer.";
        }

        $messages = [
            ['role' => 'system', 'content' => config('ai.agent.system_prompt')],
            ['role' => 'user',   'content' => $userMessage],
        ];

        return $this->llm->chat($messages);
    }

    private function formatResults(array $toolResults): string
    {
        return implode("\n\n", array_map(function (array $entry) {
            $args    = implode(', ', array_map(fn ($k, $v) => "{$k}: \"{$v}\"", array_keys($entry['args']), $entry['args']));
            $header  = "--- {$entry['tool']} ({$args}) ---";
            $content = is_array($entry['result'])
                ? json_encode($entry['result'], JSON_PRETTY_PRINT)
                : (string) $entry['result'];

            return "{$header}\n{$content}";
        }, $toolResults));
    }
}
