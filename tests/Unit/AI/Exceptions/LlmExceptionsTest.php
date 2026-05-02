<?php

namespace Tests\Unit\AI\Exceptions;

use App\Exceptions\LlmRequestFailedException;
use App\Exceptions\LlmUnavailableException;
use PHPUnit\Framework\TestCase;

class LlmExceptionsTest extends TestCase
{
    public function test_llm_unavailable_exception_extends_runtime_exception(): void
    {
        $this->assertInstanceOf(\RuntimeException::class, new LlmUnavailableException());
    }

    public function test_llm_request_failed_exception_extends_runtime_exception(): void
    {
        $this->assertInstanceOf(\RuntimeException::class, new LlmRequestFailedException());
    }

    public function test_llm_request_failed_exception_exposes_http_status(): void
    {
        $e = new LlmRequestFailedException('Bad response', 422);

        $this->assertSame(422, $e->getHttpStatus());
    }

    public function test_llm_request_failed_exception_http_status_defaults_to_null(): void
    {
        $e = new LlmRequestFailedException('Something went wrong');

        $this->assertNull($e->getHttpStatus());
    }
}
