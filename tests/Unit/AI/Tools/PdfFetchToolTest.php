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
    // chunkText
    // -------------------------------------------------------------------------

    public function test_chunk_text_returns_single_chunk_for_text_within_limit(): void
    {
        $text = str_repeat('a', 100);
        $chunks = PdfFetchTool::chunkText($text, 24000, 2000, 5);
        $this->assertCount(1, $chunks);
        $this->assertSame($text, $chunks[0]);
    }

    public function test_chunk_text_returns_single_chunk_for_text_at_exact_limit(): void
    {
        $chunks = PdfFetchTool::chunkText(str_repeat('a', 24000), 24000, 2000, 5);
        $this->assertCount(1, $chunks);
    }

    public function test_chunk_text_splits_text_that_exceeds_chunk_size(): void
    {
        $chunks = PdfFetchTool::chunkText(str_repeat('a', 24001), 24000, 2000, 5);
        $this->assertCount(2, $chunks);
    }

    public function test_chunk_text_first_chunk_is_full_chunk_size(): void
    {
        $chunks = PdfFetchTool::chunkText(str_repeat('a', 30000), 24000, 2000, 5);
        $this->assertSame(24000, mb_strlen($chunks[0]));
    }

    public function test_chunk_text_adjacent_chunks_overlap_by_specified_amount(): void
    {
        // First 22000 chars are 'a', next 4000 chars are 'b'
        $text   = str_repeat('a', 22000) . str_repeat('b', 4000);
        $chunks = PdfFetchTool::chunkText($text, 24000, 2000, 5);

        // stride = 22000; chunk[1] starts at offset 22000
        // last 2000 chars of chunk[0] = first 2000 chars of chunk[1] = 'b' repeated
        $this->assertStringEndsWith(str_repeat('b', 2000), $chunks[0]);
        $this->assertStringStartsWith(str_repeat('b', 2000), $chunks[1]);
    }

    public function test_chunk_text_is_capped_at_max_chunks(): void
    {
        // stride=22000; need >5 strides: 22000*5+1=110001 chars
        $chunks = PdfFetchTool::chunkText(str_repeat('a', 200000), 24000, 2000, 5);
        $this->assertCount(5, $chunks);
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
