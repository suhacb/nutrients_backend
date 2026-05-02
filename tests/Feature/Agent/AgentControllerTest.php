<?php

namespace Tests\Feature\Agent;

use App\AI\Contracts\LlmClientContract;
use App\Exceptions\LlmRequestFailedException;
use App\Exceptions\LlmUnavailableException;
use Mockery;
use Tests\TestCase;

class AgentControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockLlm(string $returns): void
    {
        $mock = Mockery::mock(LlmClientContract::class);
        $mock->shouldReceive('chat')->once()->andReturn($returns);
        $this->app->instance(LlmClientContract::class, $mock);
    }

    private function mockLlmThrows(\Throwable $e): void
    {
        $mock = Mockery::mock(LlmClientContract::class);
        $mock->shouldReceive('chat')->once()->andThrow($e);
        $this->app->instance(LlmClientContract::class, $mock);
    }

    private function postAgent(array $data): \Illuminate\Testing\TestResponse
    {
        return $this->withoutMiddleware()->postJson('/api/agent', $data);
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_returns_200_with_response_and_model_on_success(): void
    {
        $this->mockLlm('Magnesium supports over 300 enzymatic reactions.');

        $this->postAgent(['prompt' => 'What does magnesium do?'])
            ->assertStatus(200)
            ->assertJsonStructure(['response', 'model']);
    }

    public function test_response_contains_llm_output(): void
    {
        $this->mockLlm('Magnesium supports over 300 enzymatic reactions.');

        $this->postAgent(['prompt' => 'What does magnesium do?'])
            ->assertStatus(200)
            ->assertJsonPath('response', 'Magnesium supports over 300 enzymatic reactions.')
            ->assertJsonPath('model', config('ai.ollama.model'));
    }

    // -------------------------------------------------------------------------
    // Error handling
    // -------------------------------------------------------------------------

    public function test_returns_503_when_llm_is_unavailable(): void
    {
        $this->mockLlmThrows(new LlmUnavailableException('Connection refused'));

        $this->postAgent(['prompt' => 'What does magnesium do?'])
            ->assertStatus(503)
            ->assertJsonStructure(['message']);
    }

    public function test_returns_502_when_llm_request_fails(): void
    {
        $this->mockLlmThrows(new LlmRequestFailedException('Unexpected response', 500));

        $this->postAgent(['prompt' => 'What does magnesium do?'])
            ->assertStatus(502)
            ->assertJsonStructure(['message']);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function test_returns_422_when_prompt_is_missing(): void
    {
        $this->postAgent([])->assertStatus(422);
    }

    public function test_returns_422_when_prompt_exceeds_max_length(): void
    {
        $this->postAgent(['prompt' => str_repeat('a', 2001)])->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------

    public function test_returns_401_when_unauthenticated(): void
    {
        $this->postJson('/api/agent', ['prompt' => 'Hello'])->assertStatus(401);
    }
}
