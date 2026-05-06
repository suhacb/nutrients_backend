<?php

namespace Tests\Unit\AI\Tools;

use App\AI\Tools\PdfFetchTool;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PdfFetchToolTest extends TestCase
{
    private function makeTool(): PdfFetchTool
    {
        return new PdfFetchTool();
    }

    // -------------------------------------------------------------------------
    // Tool metadata
    // -------------------------------------------------------------------------

    public function test_name_returns_pdf_fetch(): void
    {
        $this->assertSame('pdf_fetch', $this->makeTool()->name());
    }

    public function test_description_returns_non_empty_string(): void
    {
        $this->assertNotEmpty($this->makeTool()->description());
    }

    public function test_parameters_returns_url_key(): void
    {
        $this->assertArrayHasKey('url', $this->makeTool()->parameters());
    }

    // -------------------------------------------------------------------------
    // Error handling
    // -------------------------------------------------------------------------

    public function test_throws_runtime_exception_on_http_error(): void
    {
        Http::fake(['https://example.com/doc.pdf' => Http::response('', 404)]);

        $this->expectException(\RuntimeException::class);

        $this->makeTool()->run(['url' => 'https://example.com/doc.pdf']);
    }

    public function test_throws_runtime_exception_on_connection_failure(): void
    {
        Http::fake([
            'https://example.com/doc.pdf' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $this->expectException(\RuntimeException::class);

        $this->makeTool()->run(['url' => 'https://example.com/doc.pdf']);
    }

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    public function test_fetched_url_is_logged_at_debug_level(): void
    {
        $logged = null;

        Log::shouldReceive('debug')
            ->once()
            ->andReturnUsing(function (string $message, array $ctx) use (&$logged) {
                $logged = ['message' => $message, 'ctx' => $ctx];
            });

        Http::fake(['https://example.com/doc.pdf' => Http::response('', 404)]);

        try {
            $this->makeTool()->run(['url' => 'https://example.com/doc.pdf']);
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame('pdf_fetch.url', $logged['message']);
        $this->assertSame('https://example.com/doc.pdf', $logged['ctx']['url']);
    }
}
