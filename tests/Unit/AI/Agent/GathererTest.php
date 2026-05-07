<?php

namespace Tests\Unit\AI\Agent;

use App\AI\Agent\AgentContext;
use App\AI\Agent\Gatherer;
use App\AI\Contracts\ToolContract;
use App\AI\ToolRegistry;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class GathererTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function registry(ToolContract ...$tools): ToolRegistry
    {
        $registry = new ToolRegistry();
        foreach ($tools as $tool) {
            $registry->register($tool);
        }
        return $registry;
    }

    private function searchTool(array $results): ToolContract
    {
        $tool = Mockery::mock(ToolContract::class);
        $tool->shouldReceive('name')->andReturn('web_search');
        $tool->shouldReceive('run')->andReturn($results);
        return $tool;
    }

    private function fetchTool(string $name, string $text): ToolContract
    {
        $tool = Mockery::mock(ToolContract::class);
        $tool->shouldReceive('name')->andReturn($name);
        $tool->shouldReceive('run')->andReturn($text);
        return $tool;
    }

    private function contextWithSearchPlan(): AgentContext
    {
        $ctx = new AgentContext('What is zinc?');
        $ctx->setPlan([['tool' => 'web_search', 'args' => ['query' => 'zinc benefits']]]);
        return $ctx;
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_fetches_html_source_and_adds_to_context(): void
    {
        $results = [['url' => 'https://example.com/zinc', 'title' => 'Zinc Facts', 'snippet' => '...']];

        $ctx = $this->contextWithSearchPlan();
        (new Gatherer($this->registry(
            $this->searchTool($results),
            $this->fetchTool('web_fetch', str_repeat('a', 200)),
        )))->gather($ctx);

        $this->assertCount(1, $ctx->getSources());
        $this->assertSame('https://example.com/zinc', $ctx->getSources()[0]['url']);
        $this->assertSame('Zinc Facts', $ctx->getSources()[0]['title']);
    }

    public function test_uses_pdf_fetch_tool_for_pdf_urls(): void
    {
        $results = [['url' => 'https://example.com/zinc.pdf', 'title' => 'Zinc PDF', 'snippet' => '...']];

        $pdfTool = Mockery::mock(ToolContract::class);
        $pdfTool->shouldReceive('name')->andReturn('pdf_fetch');
        $pdfTool->shouldReceive('run')->with(['url' => 'https://example.com/zinc.pdf'])->once()->andReturn(str_repeat('b', 200));

        $ctx = $this->contextWithSearchPlan();
        (new Gatherer($this->registry(
            $this->searchTool($results),
            $pdfTool,
        )))->gather($ctx);

        $this->assertCount(1, $ctx->getSources());
        $this->assertSame('https://example.com/zinc.pdf', $ctx->getSources()[0]['url']);
    }

    public function test_source_text_is_written_to_disk(): void
    {
        $text    = str_repeat('x', 200);
        $results = [['url' => 'https://example.com/zinc', 'title' => 'Zinc', 'snippet' => '...']];

        $ctx = $this->contextWithSearchPlan();
        (new Gatherer($this->registry(
            $this->searchTool($results),
            $this->fetchTool('web_fetch', $text),
        )))->gather($ctx);

        $this->assertSame($text, file_get_contents($ctx->getSources()[0]['path']));
    }

    public function test_stored_text_is_truncated_to_max_source_chars(): void
    {
        config(['ai.extraction.max_source_chars' => 150]);
        $results = [['url' => 'https://example.com/zinc', 'title' => 'Zinc', 'snippet' => '...']];

        $ctx = $this->contextWithSearchPlan();
        (new Gatherer($this->registry(
            $this->searchTool($results),
            $this->fetchTool('web_fetch', str_repeat('z', 500)),
        )))->gather($ctx);

        $this->assertSame(150, mb_strlen(file_get_contents($ctx->getSources()[0]['path'])));
    }

    public function test_gathers_all_results(): void
    {
        $results = [
            ['url' => 'https://example.com/a', 'title' => 'A', 'snippet' => '...'],
            ['url' => 'https://example.com/b', 'title' => 'B', 'snippet' => '...'],
        ];

        $fetchTool = Mockery::mock(ToolContract::class);
        $fetchTool->shouldReceive('name')->andReturn('web_fetch');
        $fetchTool->shouldReceive('run')->twice()->andReturn(str_repeat('c', 200));

        $ctx = $this->contextWithSearchPlan();
        (new Gatherer($this->registry(
            $this->searchTool($results),
            $fetchTool,
        )))->gather($ctx);

        $this->assertCount(2, $ctx->getSources());
    }

    public function test_pdf_with_multiple_chunks_adds_each_as_separate_source(): void
    {
        $results = [['url' => 'https://example.com/zinc.pdf', 'title' => 'Zinc PDF', 'snippet' => '...']];

        $pdfTool = Mockery::mock(ToolContract::class);
        $pdfTool->shouldReceive('name')->andReturn('pdf_fetch');
        $pdfTool->shouldReceive('run')->andReturn([
            str_repeat('a', 200),
            str_repeat('b', 200),
            str_repeat('c', 200),
        ]);

        $ctx = $this->contextWithSearchPlan();
        (new Gatherer($this->registry(
            $this->searchTool($results),
            $pdfTool,
        )))->gather($ctx);

        $this->assertCount(3, $ctx->getSources());
    }

    public function test_pdf_chunk_sources_all_share_the_same_url(): void
    {
        $results = [['url' => 'https://example.com/zinc.pdf', 'title' => 'Zinc PDF', 'snippet' => '...']];

        $pdfTool = Mockery::mock(ToolContract::class);
        $pdfTool->shouldReceive('name')->andReturn('pdf_fetch');
        $pdfTool->shouldReceive('run')->andReturn([
            str_repeat('a', 200),
            str_repeat('b', 200),
        ]);

        $ctx = $this->contextWithSearchPlan();
        (new Gatherer($this->registry(
            $this->searchTool($results),
            $pdfTool,
        )))->gather($ctx);

        foreach ($ctx->getSources() as $source) {
            $this->assertSame('https://example.com/zinc.pdf', $source['url']);
        }
    }

    // -------------------------------------------------------------------------
    // Filtering
    // -------------------------------------------------------------------------

    public function test_skips_results_with_too_little_content(): void
    {
        $results = [['url' => 'https://example.com/empty', 'title' => 'Empty', 'snippet' => '...']];

        $ctx = $this->contextWithSearchPlan();
        (new Gatherer($this->registry(
            $this->searchTool($results),
            $this->fetchTool('web_fetch', 'Too short.'),
        )))->gather($ctx);

        $this->assertCount(0, $ctx->getSources());
    }

    public function test_skips_results_with_missing_url(): void
    {
        $results = [['title' => 'No URL', 'snippet' => '...']];

        $ctx = $this->contextWithSearchPlan();
        (new Gatherer($this->registry(
            $this->searchTool($results),
        )))->gather($ctx);

        $this->assertCount(0, $ctx->getSources());
    }

    public function test_does_nothing_when_plan_has_no_web_search_step(): void
    {
        $ctx = new AgentContext('What is zinc?');
        $ctx->setPlan([['tool' => 'web_fetch', 'args' => ['url' => 'https://example.com']]]);

        (new Gatherer(new ToolRegistry()))->gather($ctx);

        $this->assertCount(0, $ctx->getSources());
    }

    // -------------------------------------------------------------------------
    // Error handling
    // -------------------------------------------------------------------------

    public function test_handles_search_failure_gracefully(): void
    {
        $searchTool = Mockery::mock(ToolContract::class);
        $searchTool->shouldReceive('name')->andReturn('web_search');
        $searchTool->shouldReceive('run')->andThrow(new \RuntimeException('SearXNG unavailable'));

        $ctx = $this->contextWithSearchPlan();
        (new Gatherer($this->registry($searchTool)))->gather($ctx);

        $this->assertCount(0, $ctx->getSources());
    }

    public function test_continues_gathering_after_individual_fetch_failure(): void
    {
        $results = [
            ['url' => 'https://example.com/bad',  'title' => 'Bad',  'snippet' => '...'],
            ['url' => 'https://example.com/good', 'title' => 'Good', 'snippet' => '...'],
        ];

        $fetchTool = Mockery::mock(ToolContract::class);
        $fetchTool->shouldReceive('name')->andReturn('web_fetch');
        $fetchTool->shouldReceive('run')
            ->with(['url' => 'https://example.com/bad'])->once()->andThrow(new \RuntimeException('timeout'));
        $fetchTool->shouldReceive('run')
            ->with(['url' => 'https://example.com/good'])->once()->andReturn(str_repeat('g', 200));

        $ctx = $this->contextWithSearchPlan();
        (new Gatherer($this->registry(
            $this->searchTool($results),
            $fetchTool,
        )))->gather($ctx);

        $this->assertCount(1, $ctx->getSources());
        $this->assertSame('https://example.com/good', $ctx->getSources()[0]['url']);
    }
}
