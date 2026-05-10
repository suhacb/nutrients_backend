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

    private function fetchTool(string $name, mixed $response): ToolContract
    {
        $tool = Mockery::mock(ToolContract::class);
        $tool->shouldReceive('name')->andReturn($name);
        $tool->shouldReceive('run')->andReturn($response);
        return $tool;
    }

    private function contextWithSearchPlan(): AgentContext
    {
        $ctx = new AgentContext('What is zinc?');
        $ctx->setPlan([['tool' => 'web_search', 'args' => ['query' => 'zinc benefits']]]);
        return $ctx;
    }

    private function contextWithFetchPlan(array $steps): AgentContext
    {
        $ctx = new AgentContext('What is zinc?');
        $ctx->setFetchPlan($steps);
        return $ctx;
    }

    // =========================================================================
    // search()
    // =========================================================================

    public function test_search_stores_results_in_context(): void
    {
        $results = [['url' => 'https://example.com/zinc', 'title' => 'Zinc Facts', 'snippet' => '...']];
        $ctx     = $this->contextWithSearchPlan();

        (new Gatherer($this->registry($this->searchTool($results))))->search($ctx);

        $this->assertSame($results, $ctx->getSearchResults());
    }

    public function test_search_does_nothing_when_plan_has_no_search_step(): void
    {
        $ctx = new AgentContext('What is zinc?');
        $ctx->setPlan([['tool' => 'web_fetch', 'args' => ['url' => 'https://example.com']]]);

        (new Gatherer(new ToolRegistry()))->search($ctx);

        $this->assertSame([], $ctx->getSearchResults());
    }

    public function test_search_handles_failure_gracefully(): void
    {
        $searchTool = Mockery::mock(ToolContract::class);
        $searchTool->shouldReceive('name')->andReturn('web_search');
        $searchTool->shouldReceive('run')->andThrow(new \RuntimeException('SearXNG unavailable'));

        $ctx = $this->contextWithSearchPlan();
        (new Gatherer($this->registry($searchTool)))->search($ctx);

        $this->assertSame([], $ctx->getSearchResults());
    }

    // =========================================================================
    // fetch()
    // =========================================================================

    public function test_fetch_adds_web_source_to_context(): void
    {
        $ctx = $this->contextWithFetchPlan([
            ['tool' => 'web_fetch', 'args' => ['url' => 'https://example.com/zinc']],
        ]);

        (new Gatherer($this->registry(
            $this->fetchTool('web_fetch', str_repeat('a', 200)),
        )))->fetch($ctx);

        $this->assertCount(1, $ctx->getSources());
        $this->assertSame('https://example.com/zinc', $ctx->getSources()[0]['url']);
    }

    public function test_fetch_uses_pdf_fetch_tool_when_specified(): void
    {
        $pdfTool = Mockery::mock(ToolContract::class);
        $pdfTool->shouldReceive('name')->andReturn('pdf_fetch');
        $pdfTool->shouldReceive('run')->with(['url' => 'https://example.com/zinc.pdf'])->once()->andReturn(str_repeat('b', 200));

        $ctx = $this->contextWithFetchPlan([
            ['tool' => 'pdf_fetch', 'args' => ['url' => 'https://example.com/zinc.pdf']],
        ]);

        (new Gatherer($this->registry($pdfTool)))->fetch($ctx);

        $this->assertCount(1, $ctx->getSources());
        $this->assertSame('https://example.com/zinc.pdf', $ctx->getSources()[0]['url']);
    }

    public function test_fetch_writes_source_text_to_disk(): void
    {
        $text = str_repeat('x', 200);
        $ctx  = $this->contextWithFetchPlan([
            ['tool' => 'web_fetch', 'args' => ['url' => 'https://example.com/zinc']],
        ]);

        (new Gatherer($this->registry(
            $this->fetchTool('web_fetch', $text),
        )))->fetch($ctx);

        $this->assertSame($text, file_get_contents($ctx->getSources()[0]['path']));
    }

    public function test_fetch_truncates_text_to_max_source_chars(): void
    {
        config(['ai.extraction.max_source_chars' => 150]);
        $ctx = $this->contextWithFetchPlan([
            ['tool' => 'web_fetch', 'args' => ['url' => 'https://example.com/zinc']],
        ]);

        (new Gatherer($this->registry(
            $this->fetchTool('web_fetch', str_repeat('z', 500)),
        )))->fetch($ctx);

        $this->assertSame(150, mb_strlen(file_get_contents($ctx->getSources()[0]['path'])));
    }

    public function test_fetch_executes_all_steps_in_plan(): void
    {
        $fetchTool = Mockery::mock(ToolContract::class);
        $fetchTool->shouldReceive('name')->andReturn('web_fetch');
        $fetchTool->shouldReceive('run')->twice()->andReturn(str_repeat('c', 200));

        $ctx = $this->contextWithFetchPlan([
            ['tool' => 'web_fetch', 'args' => ['url' => 'https://example.com/a']],
            ['tool' => 'web_fetch', 'args' => ['url' => 'https://example.com/b']],
        ]);

        (new Gatherer($this->registry($fetchTool)))->fetch($ctx);

        $this->assertCount(2, $ctx->getSources());
    }

    public function test_fetch_adds_each_pdf_chunk_as_separate_source(): void
    {
        $pdfTool = Mockery::mock(ToolContract::class);
        $pdfTool->shouldReceive('name')->andReturn('pdf_fetch');
        $pdfTool->shouldReceive('run')->andReturn([
            str_repeat('a', 200),
            str_repeat('b', 200),
            str_repeat('c', 200),
        ]);

        $ctx = $this->contextWithFetchPlan([
            ['tool' => 'pdf_fetch', 'args' => ['url' => 'https://example.com/zinc.pdf']],
        ]);

        (new Gatherer($this->registry($pdfTool)))->fetch($ctx);

        $this->assertCount(3, $ctx->getSources());
    }

    public function test_fetch_pdf_chunks_all_share_the_same_url(): void
    {
        $pdfTool = Mockery::mock(ToolContract::class);
        $pdfTool->shouldReceive('name')->andReturn('pdf_fetch');
        $pdfTool->shouldReceive('run')->andReturn([
            str_repeat('a', 200),
            str_repeat('b', 200),
        ]);

        $ctx = $this->contextWithFetchPlan([
            ['tool' => 'pdf_fetch', 'args' => ['url' => 'https://example.com/zinc.pdf']],
        ]);

        (new Gatherer($this->registry($pdfTool)))->fetch($ctx);

        foreach ($ctx->getSources() as $source) {
            $this->assertSame('https://example.com/zinc.pdf', $source['url']);
        }
    }

    public function test_fetch_skips_steps_with_too_little_content(): void
    {
        $ctx = $this->contextWithFetchPlan([
            ['tool' => 'web_fetch', 'args' => ['url' => 'https://example.com/empty']],
        ]);

        (new Gatherer($this->registry(
            $this->fetchTool('web_fetch', 'Too short.'),
        )))->fetch($ctx);

        $this->assertCount(0, $ctx->getSources());
    }

    public function test_fetch_does_nothing_when_fetch_plan_is_empty(): void
    {
        $ctx = new AgentContext('What is zinc?');

        (new Gatherer(new ToolRegistry()))->fetch($ctx);

        $this->assertCount(0, $ctx->getSources());
    }

    public function test_fetch_continues_after_individual_step_failure(): void
    {
        $fetchTool = Mockery::mock(ToolContract::class);
        $fetchTool->shouldReceive('name')->andReturn('web_fetch');
        $fetchTool->shouldReceive('run')
            ->with(['url' => 'https://example.com/bad'])->once()->andThrow(new \RuntimeException('timeout'));
        $fetchTool->shouldReceive('run')
            ->with(['url' => 'https://example.com/good'])->once()->andReturn(str_repeat('g', 200));

        $ctx = $this->contextWithFetchPlan([
            ['tool' => 'web_fetch', 'args' => ['url' => 'https://example.com/bad']],
            ['tool' => 'web_fetch', 'args' => ['url' => 'https://example.com/good']],
        ]);

        (new Gatherer($this->registry($fetchTool)))->fetch($ctx);

        $this->assertCount(1, $ctx->getSources());
        $this->assertSame('https://example.com/good', $ctx->getSources()[0]['url']);
    }
}
