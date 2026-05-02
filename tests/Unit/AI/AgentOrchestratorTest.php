<?php

namespace Tests\Unit\AI;

use App\AI\Agent\AgentContext;
use App\AI\Agent\Executor;
use App\AI\Agent\Planner;
use App\AI\Agent\Synthesizer;
use App\AI\AgentOrchestrator;
use App\Exceptions\LlmUnavailableException;
use Mockery;
use Tests\TestCase;

class AgentOrchestratorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makePlanner(?\Closure $callback = null): Planner
    {
        $planner = Mockery::mock(Planner::class);
        $planner->shouldReceive('plan')
            ->once()
            ->andReturnUsing($callback ?? fn (AgentContext $ctx) => null);
        return $planner;
    }

    private function makeExecutor(): Executor
    {
        $executor = Mockery::mock(Executor::class);
        $executor->shouldReceive('execute')->once();
        return $executor;
    }

    private function makeSynthesizer(string $returns): Synthesizer
    {
        $synthesizer = Mockery::mock(Synthesizer::class);
        $synthesizer->shouldReceive('synthesize')->once()->andReturn($returns);
        return $synthesizer;
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_run_returns_synthesizer_output(): void
    {
        $orchestrator = new AgentOrchestrator(
            $this->makePlanner(),
            $this->makeExecutor(),
            $this->makeSynthesizer('Magnesium supports enzyme reactions.'),
        );

        $result = $orchestrator->run('What does magnesium do?');

        $this->assertSame('Magnesium supports enzyme reactions.', $result);
    }

    public function test_run_calls_planner_executor_synthesizer_in_order(): void
    {
        $order = [];

        $planner = Mockery::mock(Planner::class);
        $planner->shouldReceive('plan')->once()
            ->andReturnUsing(function () use (&$order) { $order[] = 'planner'; });

        $executor = Mockery::mock(Executor::class);
        $executor->shouldReceive('execute')->once()
            ->andReturnUsing(function () use (&$order) { $order[] = 'executor'; });

        $synthesizer = Mockery::mock(Synthesizer::class);
        $synthesizer->shouldReceive('synthesize')->once()
            ->andReturnUsing(function () use (&$order) { $order[] = 'synthesizer'; return 'answer'; });

        (new AgentOrchestrator($planner, $executor, $synthesizer))->run('prompt');

        $this->assertSame(['planner', 'executor', 'synthesizer'], $order);
    }

    // -------------------------------------------------------------------------
    // Error handling
    // -------------------------------------------------------------------------

    public function test_llm_unavailable_exception_propagates(): void
    {
        $planner = Mockery::mock(Planner::class);
        $planner->shouldReceive('plan')->once()
            ->andThrow(new LlmUnavailableException('Connection refused'));

        $executor    = Mockery::mock(Executor::class);
        $synthesizer = Mockery::mock(Synthesizer::class);

        $this->expectException(LlmUnavailableException::class);

        (new AgentOrchestrator($planner, $executor, $synthesizer))->run('prompt');
    }
}
