<?php

namespace App\Providers;

use App\AI\Agent\Extractor;
use App\AI\Agent\Gatherer;
use App\AI\Clients\OllamaClient;
use App\AI\Contracts\LlmClientContract;
use App\AI\ToolRegistry;
use App\AI\Tools\PdfFetchTool;
use App\AI\Tools\WebFetchTool;
use App\AI\Tools\WebSearchTool;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('ai.php'), 'ai');

        $this->app->singleton(LlmClientContract::class, function () {
            return new OllamaClient(
                baseUrl: config('ai.ollama.base_url'),
                model:   config('ai.ollama.model'),
                timeout: config('ai.ollama.timeout'),
            );
        });

        $this->app->singleton(ToolRegistry::class, function () {
            $registry = new ToolRegistry();

            $registry->register(new WebSearchTool(
                baseUrl: config('ai.searxng.base_url'),
                limit:   config('ai.searxng.limit'),
                sources: config('ai.sources', []),
            ));

            $registry->register(new WebFetchTool());
            $registry->register(new PdfFetchTool());

            return $registry;
        });

        $this->app->singleton(Gatherer::class, function () {
            return new Gatherer(
                registry: $this->app->make(ToolRegistry::class),
            );
        });

        $this->app->singleton(Extractor::class, function () {
            return new Extractor(
                llm:            $this->app->make(LlmClientContract::class),
                categories:     config('ai.extraction.categories'),
                systemPrompt:   config('ai.extraction.system_prompt'),
                maxSourceChars: config('ai.extraction.max_source_chars'),
            );
        });
    }
}
