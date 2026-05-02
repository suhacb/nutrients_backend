<?php

namespace Tests\Feature\Agent;

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AgentRequestTest extends TestCase
{
    private function postAgent(array $data): TestResponse
    {
        return $this->withoutMiddleware()->postJson('/api/agent', $data);
    }

    public function test_prompt_is_required(): void
    {
        $this->postAgent([])->assertStatus(422)->assertJsonValidationErrors(['prompt']);
    }

    public function test_prompt_must_be_a_string(): void
    {
        $this->postAgent(['prompt' => 123])->assertStatus(422)->assertJsonValidationErrors(['prompt']);
    }

    public function test_prompt_cannot_exceed_2000_characters(): void
    {
        $this->postAgent(['prompt' => str_repeat('a', 2001)])->assertStatus(422)->assertJsonValidationErrors(['prompt']);
    }

    public function test_prompt_at_max_length_passes_validation(): void
    {
        $this->postAgent(['prompt' => str_repeat('a', 2000)])->assertStatus(200);
    }

    public function test_valid_prompt_passes_validation(): void
    {
        $this->postAgent(['prompt' => 'What are the benefits of magnesium?'])->assertStatus(200);
    }
}
