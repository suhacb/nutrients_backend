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
}
