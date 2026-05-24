<?php

namespace App\AI\Agent;

use App\AI\Contracts\LlmClientContract;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Extractor
{
    public function __construct(
        private readonly LlmClientContract $llm,
        private readonly array             $categories,
        private readonly string            $systemPrompt,
        private readonly int               $maxSourceChars,
    ) {}

    public function extract(AgentContext $context): void
    {
        $sources = $context->getSources();

        if (empty($sources)) {
            Log::debug('agent.extractor.no_sources');
            return;
        }

        $dir          = "agent-runs/{$context->getRunId()}/extractions";
        $categoryList = implode("\n", array_map(fn ($c) => "- {$c}", $this->categories));

        Storage::makeDirectory($dir);

        foreach ($sources as $i => $source) {
            Log::debug('agent.extractor.start', ['index' => $i, 'url' => $source['url']]);

            $text = mb_substr(file_get_contents($source['path']), 0, $this->maxSourceChars);

            $messages = [
                [
                    'role'    => 'system',
                    'content' => $this->systemPrompt,
                ],
                [
                    'role'    => 'user',
                    'content' => "Research question: {$context->getPrompt()}\n\nExtract information for these categories:\n{$categoryList}\n\nSource URL: {$source['url']}\n\nSource text:\n{$text}",
                ],
            ];

            try {
                $extraction = $this->llm->chat($messages, ['model' => config('ai.ollama.models.smart')]);
                $path       = "{$dir}/{$i}.txt";
                Storage::put($path, $extraction);
                $context->addExtraction(Storage::path($path), $i);
                Log::debug('agent.extractor.done', ['index' => $i, 'chars' => strlen($extraction)]);
            } catch (\Throwable $e) {
                Log::debug('agent.extractor.error', ['index' => $i, 'error' => $e->getMessage()]);
            }
        }
    }
}
