<?php

namespace Tests\Unit\AI\Providers;

use App\AI\Agent\Extractor;
use App\AI\Agent\Gatherer;
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

    public function test_resolved_llm_client_is_a_singleton(): void
    {
        $this->assertSame(app(LlmClientContract::class), app(LlmClientContract::class));
    }

    public function test_tool_registry_resolves_from_container(): void
    {
        $this->assertInstanceOf(ToolRegistry::class, app(ToolRegistry::class));
    }

    public function test_tool_registry_is_singleton(): void
    {
        $this->assertSame(app(ToolRegistry::class), app(ToolRegistry::class));
    }

    public function test_registry_has_web_search_tool_registered(): void
    {
        $this->assertSame('web_search', app(ToolRegistry::class)->resolve('web_search')->name());
    }

    public function test_registry_has_web_fetch_tool_registered(): void
    {
        $this->assertSame('web_fetch', app(ToolRegistry::class)->resolve('web_fetch')->name());
    }

    public function test_registry_has_pdf_fetch_tool_registered(): void
    {
        $this->assertSame('pdf_fetch', app(ToolRegistry::class)->resolve('pdf_fetch')->name());
    }

    public function test_gatherer_resolves_from_container(): void
    {
        $this->assertInstanceOf(Gatherer::class, app(Gatherer::class));
    }

    public function test_gatherer_is_singleton(): void
    {
        $this->assertSame(app(Gatherer::class), app(Gatherer::class));
    }

    public function test_extractor_resolves_from_container(): void
    {
        $this->assertInstanceOf(Extractor::class, app(Extractor::class));
    }

    public function test_extractor_is_singleton(): void
    {
        $this->assertSame(app(Extractor::class), app(Extractor::class));
    }
}
