<?php

namespace App\AI\Clients;

use App\AI\Contracts\LlmClientContract;
use App\Exceptions\LlmRequestFailedException;
use App\Exceptions\LlmUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OllamaClient implements LlmClientContract
{
    private int $lastInputTokens  = 0;
    private int $lastOutputTokens = 0;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int    $timeout,
    ) {}

    public function generate(string $prompt, array $options = []): string
    {
        $payload = array_merge([
            'model'  => $this->model,
            'prompt' => $prompt,
            'stream' => false,
        ], $options);

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/generate", $payload);
        } catch (ConnectionException $e) {
            throw new LlmUnavailableException($e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new LlmRequestFailedException(
                "Ollama generate request failed with status {$response->status()}.",
                $response->status(),
            );
        }

        $text = $response->json('response');

        if ($text === null) {
            throw new LlmRequestFailedException('Ollama response missing expected "response" key.');
        }

        return $text;
    }

    public function chat(array $messages, array $options = []): string
    {
        $model = $options['model'] ?? $this->model;
        $this->ensureModel($model);

        $payload = array_merge([
            'model'    => $model,
            'messages' => $messages,
            'stream'   => false,
        ], $options);

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/chat", $payload);
        } catch (ConnectionException $e) {
            throw new LlmUnavailableException($e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new LlmRequestFailedException(
                "Ollama chat request failed with status {$response->status()}.",
                $response->status(),
            );
        }

        $text = $response->json('message.content');

        if ($text === null) {
            throw new LlmRequestFailedException('Ollama response missing expected "message.content" key.');
        }

        $this->lastInputTokens  = (int) $response->json('prompt_eval_count', 0);
        $this->lastOutputTokens = (int) $response->json('eval_count', 0);

        return $text;
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/tags");
            return $response->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    public function getLastUsage(): array
    {
        return ['input' => $this->lastInputTokens, 'output' => $this->lastOutputTokens];
    }

    public function unload(): void
    {
        try {
            Http::timeout(10)->post("{$this->baseUrl}/api/generate", [
                'model'      => $this->model,
                'keep_alive' => 0,
            ]);
        } catch (\Throwable) {
            // Best-effort — don't fail the job if unload doesn't respond.
        }
    }

    private function loadedModels(): array
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/ps");
            return $response->successful()
                ? array_column($response->json('models', []), 'name')
                : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function ensureModel(string $model): void
    {
        $loaded = $this->loadedModels();

        if (in_array($model, $loaded)) {
            return;
        }

        foreach ($loaded as $loadedModel) {
            try {
                Http::timeout(10)->post("{$this->baseUrl}/api/generate", [
                    'model'      => $loadedModel,
                    'keep_alive' => 0,
                ]);
            } catch (\Throwable) {
                // Best-effort — don't fail if unload doesn't respond.
            }
        }
    }
}
