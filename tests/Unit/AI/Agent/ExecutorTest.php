<?php

namespace Tests\Unit\AI\Agent;

use App\AI\Agent\AgentContext;
use App\AI\Agent\Executor;
use App\AI\Contracts\ToolContract;
use App\AI\ToolRegistry;
use Mockery;
use Tests\TestCase;

class ExecutorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeTool(string $name, mixed $result): ToolContract
    {
        $tool = Mockery::mock(ToolContract::class);
        $tool->shouldReceive('name')->andReturn($name);
        $tool->shouldReceive('run')->andReturn($result);
        return $tool;
    }

    private function makeRegistry(array $tools = []): ToolRegistry
    {
        $registry = Mockery::mock(ToolRegistry::class);

        foreach ($tools as $name => $tool) {
            $registry->shouldReceive('resolve')->with($name)->andReturn($tool);
        }

        return $registry;
    }

    private function contextWithPlan(array $plan): AgentContext
    {
        $context = new AgentContext('What does magnesium do?');
        $context->setPlan($plan);
        return $context;
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_each_step_is_executed_via_correct_tool(): void
    {
        $tool = Mockery::mock(ToolContract::class);
        $tool->shouldReceive('run')->once()->with(['query' => 'magnesium'])->andReturn([]);

        $registry = $this->makeRegistry(['web_search' => $tool]);
        $context  = $this->contextWithPlan([
            ['tool' => 'web_search', 'args' => ['query' => 'magnesium']],
        ]);

        (new Executor($registry))->execute($context);

        $this->assertCount(1, $context->getToolResults());
    }

    public function test_results_are_stored_in_context(): void
    {
        $result   = [['url' => 'https://example.com', 'title' => 'Example', 'snippet' => 'Text']];
        $tool     = $this->makeTool('web_search', $result);
        $registry = $this->makeRegistry(['web_search' => $tool]);
        $context  = $this->contextWithPlan([
            ['tool' => 'web_search', 'args' => ['query' => 'magnesium']],
        ]);

        (new Executor($registry))->execute($context);

        $this->assertCount(1, $context->getToolResults());
        $this->assertSame('web_search', $context->getToolResults()[0]['tool']);
        $this->assertSame($result, $context->getToolResults()[0]['result']);
    }

    // -------------------------------------------------------------------------
    // Fault tolerance
    // -------------------------------------------------------------------------

    public function test_unknown_tool_name_is_skipped(): void
    {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('resolve')
            ->with('unknown_tool')
            ->andThrow(new \InvalidArgumentException("Tool 'unknown_tool' is not registered."));

        $context = $this->contextWithPlan([
            ['tool' => 'unknown_tool', 'args' => []],
        ]);

        (new Executor($registry))->execute($context);

        $this->assertSame([], $context->getToolResults());
    }

    public function test_tool_exception_is_skipped(): void
    {
        $tool = Mockery::mock(ToolContract::class);
        $tool->shouldReceive('run')->andThrow(new \RuntimeException('Connection failed'));

        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('resolve')->with('web_search')->andReturn($tool);

        $context = $this->contextWithPlan([
            ['tool' => 'web_search', 'args' => ['query' => 'magnesium']],
        ]);

        (new Executor($registry))->execute($context);

        $this->assertSame([], $context->getToolResults());
    }

    public function test_empty_plan_calls_no_tools(): void
    {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldNotReceive('resolve');

        $context = $this->contextWithPlan([]);

        (new Executor($registry))->execute($context);

        $this->assertSame([], $context->getToolResults());
    }
}
