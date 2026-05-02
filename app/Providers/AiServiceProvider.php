<?php

namespace App\Providers;

use App\AI\Clients\OllamaClient;
use App\AI\Contracts\LlmClientContract;
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
    }
}
