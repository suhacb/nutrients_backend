<?php

namespace Tests\Unit\AI\Agent;

use App\AI\Agent\AgentContext;
use PHPUnit\Framework\TestCase;

class AgentContextTest extends TestCase
{
    public function test_get_prompt_returns_constructor_value(): void
    {
        $context = new AgentContext('What are the benefits of magnesium?');

        $this->assertSame('What are the benefits of magnesium?', $context->getPrompt());
    }

    public function test_get_plan_returns_empty_array_by_default(): void
    {
        $context = new AgentContext('prompt');

        $this->assertSame([], $context->getPlan());
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

    public function test_get_tool_results_returns_empty_array_by_default(): void
    {
        $context = new AgentContext('prompt');

        $this->assertSame([], $context->getToolResults());
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
}
