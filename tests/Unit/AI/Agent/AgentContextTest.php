<?php

namespace Tests\Unit\AI\Agent;

use App\AI\Agent\AgentContext;
use PHPUnit\Framework\TestCase;

class AgentContextTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Prompt
    // -------------------------------------------------------------------------

    public function test_get_prompt_returns_constructor_value(): void
    {
        $context = new AgentContext('What are the benefits of magnesium?');

        $this->assertSame('What are the benefits of magnesium?', $context->getPrompt());
    }

    // -------------------------------------------------------------------------
    // Run ID
    // -------------------------------------------------------------------------

    public function test_get_run_id_returns_non_empty_string(): void
    {
        $this->assertNotEmpty((new AgentContext('prompt'))->getRunId());
    }

    public function test_run_id_is_unique_per_instance(): void
    {
        $a = new AgentContext('prompt');
        $b = new AgentContext('prompt');

        $this->assertNotSame($a->getRunId(), $b->getRunId());
    }

    // -------------------------------------------------------------------------
    // Plan
    // -------------------------------------------------------------------------

    public function test_get_plan_returns_empty_array_by_default(): void
    {
        $this->assertSame([], (new AgentContext('prompt'))->getPlan());
    }

    public function test_set_plan_and_get_plan_round_trips(): void
    {
        $context = new AgentContext('prompt');
        $plan    = [
            ['tool' => 'web_search', 'args' => ['query' => 'magnesium']],
            ['tool' => 'web_fetch',  'args' => ['url'   => 'https://example.com']],
        ];

        $context->setPlan($plan);

        $this->assertSame($plan, $context->getPlan());
    }

    // -------------------------------------------------------------------------
    // Search results
    // -------------------------------------------------------------------------

    public function test_get_search_results_returns_empty_array_by_default(): void
    {
        $this->assertSame([], (new AgentContext('prompt'))->getSearchResults());
    }

    public function test_set_search_results_and_get_round_trips(): void
    {
        $context = new AgentContext('prompt');
        $results = [
            ['url' => 'https://a.com', 'title' => 'A', 'snippet' => '...'],
            ['url' => 'https://b.com', 'title' => 'B', 'snippet' => '...'],
        ];

        $context->setSearchResults($results);

        $this->assertSame($results, $context->getSearchResults());
    }

    // -------------------------------------------------------------------------
    // Fetch plan
    // -------------------------------------------------------------------------

    public function test_get_fetch_plan_returns_empty_array_by_default(): void
    {
        $this->assertSame([], (new AgentContext('prompt'))->getFetchPlan());
    }

    public function test_set_fetch_plan_and_get_round_trips(): void
    {
        $context = new AgentContext('prompt');
        $plan    = [
            ['tool' => 'web_fetch',  'args' => ['url' => 'https://example.com']],
            ['tool' => 'pdf_fetch',  'args' => ['url' => 'https://example.com/doc.pdf']],
        ];

        $context->setFetchPlan($plan);

        $this->assertSame($plan, $context->getFetchPlan());
    }

    // -------------------------------------------------------------------------
    // Tool results (kept for backward compatibility)
    // -------------------------------------------------------------------------

    public function test_get_tool_results_returns_empty_array_by_default(): void
    {
        $this->assertSame([], (new AgentContext('prompt'))->getToolResults());
    }

    public function test_add_tool_result_appends_entry(): void
    {
        $context = new AgentContext('prompt');
        $result  = [['url' => 'https://example.com', 'title' => 'Example', 'snippet' => 'Text']];

        $context->addToolResult('web_search', ['query' => 'magnesium'], $result);

        $this->assertCount(1, $context->getToolResults());
        $this->assertSame([
            'tool'   => 'web_search',
            'args'   => ['query' => 'magnesium'],
            'result' => $result,
        ], $context->getToolResults()[0]);
    }

    public function test_multiple_tool_results_are_stored_in_order(): void
    {
        $context = new AgentContext('prompt');

        $context->addToolResult('web_search', ['query' => 'magnesium'], ['result1']);
        $context->addToolResult('web_fetch',  ['url'   => 'https://example.com'], 'fetched text');

        $results = $context->getToolResults();

        $this->assertCount(2, $results);
        $this->assertSame('web_search', $results[0]['tool']);
        $this->assertSame('web_fetch',  $results[1]['tool']);
    }

    // -------------------------------------------------------------------------
    // Sources
    // -------------------------------------------------------------------------

    public function test_get_sources_returns_empty_array_by_default(): void
    {
        $this->assertSame([], (new AgentContext('prompt'))->getSources());
    }

    public function test_add_source_appends_entry_with_path_url_and_title(): void
    {
        $context = new AgentContext('prompt');
        $context->addSource('/tmp/source.txt', 'https://example.com', 'Example');

        $sources = $context->getSources();
        $this->assertCount(1, $sources);
        $this->assertSame('/tmp/source.txt', $sources[0]['path']);
        $this->assertSame('https://example.com', $sources[0]['url']);
        $this->assertSame('Example', $sources[0]['title']);
    }

    public function test_multiple_sources_are_stored_in_order(): void
    {
        $context = new AgentContext('prompt');
        $context->addSource('/tmp/a.txt', 'https://a.com', 'A');
        $context->addSource('/tmp/b.txt', 'https://b.com', 'B');

        $sources = $context->getSources();
        $this->assertCount(2, $sources);
        $this->assertSame('https://a.com', $sources[0]['url']);
        $this->assertSame('https://b.com', $sources[1]['url']);
    }

    // -------------------------------------------------------------------------
    // Extractions
    // -------------------------------------------------------------------------

    public function test_get_extractions_returns_empty_array_by_default(): void
    {
        $this->assertSame([], (new AgentContext('prompt'))->getExtractions());
    }

    public function test_add_extraction_appends_entry_with_path_and_source_index(): void
    {
        $context = new AgentContext('prompt');
        $context->addExtraction('/tmp/extraction.txt', 2);

        $extractions = $context->getExtractions();
        $this->assertCount(1, $extractions);
        $this->assertSame('/tmp/extraction.txt', $extractions[0]['path']);
        $this->assertSame(2, $extractions[0]['sourceIndex']);
    }

    public function test_multiple_extractions_are_stored_in_order(): void
    {
        $context = new AgentContext('prompt');
        $context->addExtraction('/tmp/e0.txt', 0);
        $context->addExtraction('/tmp/e1.txt', 1);

        $extractions = $context->getExtractions();
        $this->assertCount(2, $extractions);
        $this->assertSame(0, $extractions[0]['sourceIndex']);
        $this->assertSame(1, $extractions[1]['sourceIndex']);
    }
}
