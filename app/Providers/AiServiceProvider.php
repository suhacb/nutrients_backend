<?php

namespace App\Providers;

use App\AI\Clients\OllamaClient;
use App\AI\Contracts\LlmClientContract;
use App\AI\ToolRegistry;
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

            return $registry;
        });
    }
}
