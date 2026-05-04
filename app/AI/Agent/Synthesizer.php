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
        $extractions = $context->getExtractions();

        if (empty($extractions)) {
            return $this->llm->chat([
                ['role' => 'system', 'content' => config('ai.agent.system_prompt')],
                ['role' => 'user',   'content' => $context->getPrompt()],
            ]);
        }

        $sources          = $context->getSources();
        $extractionBlocks = [];

        foreach ($extractions as $i => ['path' => $path, 'sourceIndex' => $si]) {
            $url                = $sources[$si]['url'] ?? 'unknown';
            $extractionBlocks[] = '--- Source ' . ($i + 1) . " ({$url}) ---\n" . file_get_contents($path);
        }

        $categories   = config('ai.extraction.categories', []);
        $categoryList = implode(', ', $categories);

        $messages = [
            [
                'role'    => 'system',
                'content' => config('ai.agent.system_prompt'),
            ],
            [
                'role'    => 'user',
                'content' => "Question: {$context->getPrompt()}\n\nCover these aspects: {$categoryList}\n\nResearch extractions from " . count($extractions) . " sources:\n\n" . implode("\n\n", $extractionBlocks) . "\n\nWrite a comprehensive, well-structured answer based on the research above.",
            ],
        ];

        return $this->llm->chat($messages);
    }
}
