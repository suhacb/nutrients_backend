<?php

namespace Tests\Unit\AI\Tools;

use App\AI\Tools\WebSearchTool;
use App\Exceptions\LlmUnavailableException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebSearchToolTest extends TestCase
{
    private string $baseUrl = 'http://searxng.test';
    private int $limit = 3;

    private function makeTool(): WebSearchTool
    {
        return new WebSearchTool(baseUrl: $this->baseUrl, limit: $this->limit);
    }

    private function searchUrl(): string
    {
        return $this->baseUrl . '/search';
    }

    private function fakeResults(int $count): array
    {
        return array_map(fn ($i) => [
            'url'     => "https://example.com/result-{$i}",
            'title'   => "Result {$i}",
            'content' => "Snippet {$i}",
        ], range(1, $count));
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_returns_array_of_results_on_success(): void
    {
        Http::fake([
            $this->searchUrl() . '*' => Http::response([
                'results' => $this->fakeResults(2),
            ], 200),
        ]);

        $results = $this->makeTool()->run(['query' => 'magnesium']);

        $this->assertCount(2, $results);
        $this->assertSame('https://example.com/result-1', $results[0]['url']);
        $this->assertSame('Result 1', $results[0]['title']);
        $this->assertSame('Snippet 1', $results[0]['snippet']);
    }

    public function test_result_count_is_limited_to_configured_limit(): void
    {
        Http::fake([
            $this->searchUrl() . '*' => Http::response([
                'results' => $this->fakeResults(10),
            ], 200),
        ]);

        $results = $this->makeTool()->run(['query' => 'magnesium']);

        $this->assertCount($this->limit, $results);
    }

    public function test_returns_empty_array_when_no_results(): void
    {
        Http::fake([
            $this->searchUrl() . '*' => Http::response(['results' => []], 200),
        ]);

        $this->assertSame([], $this->makeTool()->run(['query' => 'magnesium']));
    }

    public function test_returns_empty_array_when_results_key_missing(): void
    {
        Http::fake([
            $this->searchUrl() . '*' => Http::response(['query' => 'magnesium'], 200),
        ]);

        $this->assertSame([], $this->makeTool()->run(['query' => 'magnesium']));
    }

    // -------------------------------------------------------------------------
    // Error handling
    // -------------------------------------------------------------------------

    public function test_throws_runtime_exception_on_http_error(): void
    {
        Http::fake([
            $this->searchUrl() . '*' => Http::response([], 500),
        ]);

        $this->expectException(\RuntimeException::class);

        $this->makeTool()->run(['query' => 'magnesium']);
    }

    public function test_throws_llm_unavailable_exception_on_connection_failure(): void
    {
        Http::fake([
            $this->searchUrl() . '*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'),
        ]);

        $this->expectException(LlmUnavailableException::class);

        $this->makeTool()->run(['query' => 'magnesium']);
    }

    // -------------------------------------------------------------------------
    // Tool metadata
    // -------------------------------------------------------------------------

    public function test_name_returns_web_search(): void
    {
        $this->assertSame('web_search', $this->makeTool()->name());
    }

    public function test_description_returns_non_empty_string(): void
    {
        $this->assertNotEmpty($this->makeTool()->description());
    }

    public function test_parameters_returns_query_key(): void
    {
        $this->assertArrayHasKey('query', $this->makeTool()->parameters());
    }
}
