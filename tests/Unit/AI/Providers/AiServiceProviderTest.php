<?php

namespace Tests\Unit\AI\Providers;

use App\AI\Clients\OllamaClient;
use App\AI\Contracts\LlmClientContract;
use App\AI\ToolRegistry;
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

    public function test_tool_registry_resolves_from_container(): void
    {
        $this->assertInstanceOf(ToolRegistry::class, app(ToolRegistry::class));
    }

    public function test_tool_registry_is_singleton(): void
    {
        $first  = app(ToolRegistry::class);
        $second = app(ToolRegistry::class);

        $this->assertSame($first, $second);
    }

    public function test_registry_has_web_search_tool_registered(): void
    {
        $registry = app(ToolRegistry::class);

        $this->assertSame('web_search', $registry->resolve('web_search')->name());
    }

    public function test_registry_has_web_fetch_tool_registered(): void
    {
        $registry = app(ToolRegistry::class);

        $this->assertSame('web_fetch', $registry->resolve('web_fetch')->name());
    }
}
