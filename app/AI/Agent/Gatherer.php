<?php

namespace App\AI\Agent;

use App\AI\ToolRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Gatherer
{
    public function __construct(
        private readonly ToolRegistry $registry,
    ) {}

    public function gather(AgentContext $context): void
    {
        $searchStep = null;
        foreach ($context->getPlan() as $step) {
            if ($step['tool'] === 'web_search') {
                $searchStep = $step;
                break;
            }
        }

        if ($searchStep === null) {
            Log::debug('agent.gatherer.no_search_step');
            return;
        }

        Log::debug('agent.gatherer.search_start', ['args' => $searchStep['args']]);

        try {
            $results = $this->registry->resolve('web_search')->run($searchStep['args']);
        } catch (\Throwable $e) {
            Log::debug('agent.gatherer.search_error', ['error' => $e->getMessage()]);
            return;
        }

        Log::debug('agent.gatherer.search_done', ['count' => count($results)]);

        $dir = "agent-runs/{$context->getRunId()}/sources";
        Storage::makeDirectory($dir);

        foreach ($results as $i => $result) {
            $url = $result['url'] ?? null;
            if (!$url) {
                continue;
            }

            $isPdf    = str_ends_with(strtolower(parse_url($url, PHP_URL_PATH) ?? ''), '.pdf');
            $toolName = $isPdf ? 'pdf_fetch' : 'web_fetch';

            Log::debug('agent.gatherer.fetch_start', ['index' => $i, 'url' => $url, 'type' => $toolName]);

            try {
                $toolResult = $this->registry->resolve($toolName)->run(['url' => $url]);

                $chunks = is_array($toolResult)
                    ? $toolResult
                    : [mb_substr((string) $toolResult, 0, config('ai.extraction.max_source_chars'))];

                foreach ($chunks as $chunkIndex => $chunkText) {
                    if (mb_strlen(trim($chunkText)) < 100) {
                        Log::debug('agent.gatherer.fetch_empty', ['index' => $i, 'chunk' => $chunkIndex, 'url' => $url]);
                        continue;
                    }

                    Storage::put("{$dir}/{$i}_{$chunkIndex}.txt", $chunkText);
                    $context->addSource(Storage::path("{$dir}/{$i}_{$chunkIndex}.txt"), $url, $result['title'] ?? '');
                }

                Log::debug('agent.gatherer.fetch_done', ['index' => $i, 'url' => $url, 'chunks' => count($chunks)]);
            } catch (\Throwable $e) {
                Log::debug('agent.gatherer.fetch_error', ['index' => $i, 'url' => $url, 'error' => $e->getMessage()]);
            }
        }

        Log::debug('agent.gatherer.complete', ['sources_stored' => count($context->getSources())]);
    }
}
