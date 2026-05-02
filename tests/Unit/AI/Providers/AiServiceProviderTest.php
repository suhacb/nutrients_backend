<?php

namespace Tests\Unit\AI\Providers;

use App\AI\Clients\OllamaClient;
use App\AI\Contracts\LlmClientContract;
use Tests\TestCase;

class AiServiceProviderTest extends TestCase
{
    public function test_llm_client_contract_resolves_to_ollama_client(): void
    {
        $this->assertInstanceOf(OllamaClient::class, app(LlmClientContract::class));
    }

    public function test_resolved_instance_is_a_singleton(): void
    {
        $first  = app(LlmClientContract::class);
        $second = app(LlmClientContract::class);

        $this->assertSame($first, $second);
    }

    public function test_ollama_client_is_configured_from_config(): void
    {
        /** @var OllamaClient $client */
        $client = app(LlmClientContract::class);

        $this->assertInstanceOf(OllamaClient::class, $client);
    }
}
