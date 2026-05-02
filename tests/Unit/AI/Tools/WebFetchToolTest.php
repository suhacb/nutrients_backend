<?php

namespace Tests\Unit\AI\Tools;

use App\AI\Tools\WebFetchTool;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebFetchToolTest extends TestCase
{
    private function makeTool(): WebFetchTool
    {
        return new WebFetchTool();
    }

    private function fetch(string $html): string
    {
        Http::fake(['https://example.com' => Http::response($html, 200)]);

        return $this->makeTool()->run(['url' => 'https://example.com']);
    }

    // -------------------------------------------------------------------------
    // Content extraction
    // -------------------------------------------------------------------------

    public function test_extracts_paragraphs_from_article_element(): void
    {
        $result = $this->fetch('<html><body><article><p>First.</p><p>Second.</p></article></body></html>');

        $this->assertStringContainsString('First.', $result);
        $this->assertStringContainsString('Second.', $result);
    }

    public function test_extracts_paragraphs_from_main_element_when_no_article(): void
    {
        $result = $this->fetch('<html><body><main><p>Main content.</p></main></body></html>');

        $this->assertStringContainsString('Main content.', $result);
    }

    public function test_extracts_paragraphs_from_body_when_no_article_or_main(): void
    {
        $result = $this->fetch('<html><body><p>Body content.</p></body></html>');

        $this->assertStringContainsString('Body content.', $result);
    }

    public function test_excludes_script_nav_and_footer_content(): void
    {
        $html = '<html><body>
            <script>alert("x")</script>
            <nav><p>Nav link</p></nav>
            <footer><p>Footer text</p></footer>
            <article><p>Real content.</p></article>
        </body></html>';

        $result = $this->fetch($html);

        $this->assertStringContainsString('Real content.', $result);
        $this->assertStringNotContainsString('Nav link', $result);
        $this->assertStringNotContainsString('Footer text', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    public function test_returns_empty_string_when_no_extractable_content(): void
    {
        $result = $this->fetch('<html><body><div>No paragraphs here.</div></body></html>');

        $this->assertSame('', $result);
    }

    // -------------------------------------------------------------------------
    // Error handling
    // -------------------------------------------------------------------------

    public function test_throws_runtime_exception_on_http_error(): void
    {
        Http::fake(['https://example.com' => Http::response('', 404)]);

        $this->expectException(\RuntimeException::class);

        $this->makeTool()->run(['url' => 'https://example.com']);
    }

    public function test_throws_runtime_exception_on_connection_failure(): void
    {
        Http::fake([
            'https://example.com' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'),
        ]);

        $this->expectException(\RuntimeException::class);

        $this->makeTool()->run(['url' => 'https://example.com']);
    }

    // -------------------------------------------------------------------------
    // Tool metadata
    // -------------------------------------------------------------------------

    public function test_name_returns_web_fetch(): void
    {
        $this->assertSame('web_fetch', $this->makeTool()->name());
    }

    public function test_description_returns_non_empty_string(): void
    {
        $this->assertNotEmpty($this->makeTool()->description());
    }

    public function test_parameters_returns_url_key(): void
    {
        $this->assertArrayHasKey('url', $this->makeTool()->parameters());
    }
}
