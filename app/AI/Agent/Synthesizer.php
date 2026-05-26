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
            ], array_merge(['model' => config('ai.ollama.models.smart')], config('ai.agent.llm_fallback_options', [])));
        }

        $sources          = $context->getSources();
        $extractionBlocks = [];

        foreach ($extractions as $i => ['path' => $path, 'sourceIndex' => $si]) {
            $url                = $sources[$si]['url'] ?? 'unknown';
            $extractionBlocks[] = '--- Source ' . ($i + 1) . " ({$url}) ---\n" . file_get_contents($path);
        }

        $categories    = config('ai.extraction.categories', []);
        $firstCategory = $categories[0] ?? '';
        $sectionList   = implode("\n", array_map(fn ($c) => "## {$c}", $categories));

        $messages = [
            [
                'role'    => 'system',
                'content' => config('ai.agent.system_prompt'),
            ],
            [
                'role'    => 'user',
                'content' => "Question: {$context->getPrompt()}\n\nResearch extractions from " . count($extractions) . " sources:\n\n" . implode("\n\n", $extractionBlocks) . "\n\nWrite a comprehensive, well-structured markdown answer using exactly these sections:\n\n{$sectionList}\n\nStart directly with ## {$firstCategory} — no preamble or introduction before it. No closing remarks or disclaimer at the end.",
            ],
        ];

        return $this->llm->chat($messages, array_merge(['model' => config('ai.ollama.models.smart')], config('ai.agent.llm_options', [])));
    }
}
