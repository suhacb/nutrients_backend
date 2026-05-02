<?php

namespace App\AI\Clients;

use App\AI\Contracts\LlmClientContract;
use App\Exceptions\LlmRequestFailedException;
use App\Exceptions\LlmUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OllamaClient implements LlmClientContract
{
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
        $payload = array_merge([
            'model'    => $this->model,
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
}
