<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\LlmClientContract;
use Tests\TestCase;

class OllamaClientLiveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!env('OLLAMA_TESTING')) {
            $this->markTestSkipped('Live Ollama tests are disabled. Set OLLAMA_TESTING=true to run.');
        }
    }

    public function test_is_available_returns_true(): void
    {
        $this->assertTrue(app(LlmClientContract::class)->isAvailable());
    }

    public function test_generate_returns_non_empty_string(): void
    {
        $result = app(LlmClientContract::class)->generate('In one sentence, what is vitamin C?');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function test_chat_returns_non_empty_string(): void
    {
        $result = app(LlmClientContract::class)->chat([
            ['role' => 'system', 'content' => 'You are a nutritionist. Answer in one sentence.'],
            ['role' => 'user',   'content' => 'What is the role of iron in the human body?'],
        ]);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }
}
