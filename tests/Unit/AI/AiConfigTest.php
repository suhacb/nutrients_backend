<?php

namespace Tests\Unit\AI;

use Tests\TestCase;

class AiConfigTest extends TestCase
{
    public function test_searxng_base_url_is_a_non_empty_string(): void
    {
        $value = config('ai.searxng.base_url');

        $this->assertIsString($value);
        $this->assertNotEmpty($value);
    }

    public function test_searxng_limit_is_a_positive_integer(): void
    {
        $value = config('ai.searxng.limit');

        $this->assertIsInt($value);
        $this->assertGreaterThan(0, $value);
    }

    public function test_extraction_categories_are_exactly_the_six_canonical_sections(): void
    {
        $expected = [
            'Overview',
            'Role in the body and metabolism',
            'Health benefits',
            'Recommended intake and dosage',
            'Supplementation',
            'Interactions and contraindications',
        ];

        $this->assertSame($expected, config('ai.extraction.categories'));
    }

    public function test_agent_system_prompt_forbids_emojis(): void
    {
        $prompt = config('ai.agent.system_prompt');
        $this->assertStringContainsStringIgnoringCase('emoji', $prompt);
    }

    public function test_agent_system_prompt_forbids_preamble(): void
    {
        $prompt = config('ai.agent.system_prompt');
        $this->assertMatchesRegularExpression('/no.*(introduction|overview paragraph|preamble)/i', $prompt);
    }

    public function test_agent_system_prompt_forbids_disclaimers(): void
    {
        $prompt = config('ai.agent.system_prompt');
        $this->assertStringContainsStringIgnoringCase('disclaimer', $prompt);
    }
}
