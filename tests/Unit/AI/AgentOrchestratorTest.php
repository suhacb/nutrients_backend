<?php

namespace Tests\Unit\AI;

use App\AI\Agent\AgentContext;
use App\AI\Agent\Extractor;
use App\AI\Agent\Gatherer;
use App\AI\Agent\Planner;
use App\AI\Agent\Synthesizer;
use App\AI\AgentOrchestrator;
use App\AI\Contracts\LlmClientContract;
use App\Exceptions\LlmUnavailableException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AgentOrchestratorTest extends TestCase
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

    private function makePlanner(?\Closure $planCallback = null): Planner
    {
        $planner = Mockery::mock(Planner::class);
        $planner->shouldReceive('plan')->once()->andReturnUsing($planCallback ?? fn () => null);
        $planner->shouldReceive('planFetches')->once();
        return $planner;
    }

    private function makeGatherer(): Gatherer
    {
        $gatherer = Mockery::mock(Gatherer::class);
        $gatherer->shouldReceive('search')->once();
        $gatherer->shouldReceive('fetch')->once();
        return $gatherer;
    }

    private function makeExtractor(): Extractor
    {
        $extractor = Mockery::mock(Extractor::class);
        $extractor->shouldReceive('extract')->once();
        return $extractor;
    }

    private function makeSynthesizer(string $returns): Synthesizer
    {
        $synthesizer = Mockery::mock(Synthesizer::class);
        $synthesizer->shouldReceive('synthesize')->once()->andReturn($returns);
        return $synthesizer;
    }

    private function makeLlm(): LlmClientContract
    {
        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('unload')->zeroOrMoreTimes();
        return $llm;
    }

    private function makeOrchestrator(
        ?Planner $planner = null,
        ?Gatherer $gatherer = null,
        ?Extractor $extractor = null,
        ?Synthesizer $synthesizer = null,
        ?LlmClientContract $llm = null,
    ): AgentOrchestrator {
        return new AgentOrchestrator(
            $planner     ?? $this->makePlanner(),
            $gatherer    ?? $this->makeGatherer(),
            $extractor   ?? $this->makeExtractor(),
            $synthesizer ?? $this->makeSynthesizer('answer'),
            $llm         ?? $this->makeLlm(),
        );
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_run_returns_synthesizer_output(): void
    {
        $result = $this->makeOrchestrator(synthesizer: $this->makeSynthesizer('Zinc supports immunity.'))->run('What does zinc do?');

        $this->assertSame('Zinc supports immunity.', $result);
    }

    public function test_run_calls_pipeline_in_correct_order(): void
    {
        $order = [];

        $planner = Mockery::mock(Planner::class);
        $planner->shouldReceive('plan')->once()
            ->andReturnUsing(function () use (&$order) { $order[] = 'planner.plan'; });
        $planner->shouldReceive('planFetches')->once()
            ->andReturnUsing(function () use (&$order) { $order[] = 'planner.planFetches'; });

        $gatherer = Mockery::mock(Gatherer::class);
        $gatherer->shouldReceive('search')->once()
            ->andReturnUsing(function () use (&$order) { $order[] = 'gatherer.search'; });
        $gatherer->shouldReceive('fetch')->once()
            ->andReturnUsing(function () use (&$order) { $order[] = 'gatherer.fetch'; });

        $extractor = Mockery::mock(Extractor::class);
        $extractor->shouldReceive('extract')->once()
            ->andReturnUsing(function () use (&$order) { $order[] = 'extractor'; });

        $synthesizer = Mockery::mock(Synthesizer::class);
        $synthesizer->shouldReceive('synthesize')->once()
            ->andReturnUsing(function () use (&$order) { $order[] = 'synthesizer'; return 'answer'; });

        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('unload')->once();

        (new AgentOrchestrator($planner, $gatherer, $extractor, $synthesizer, $llm))->run('prompt');

        $this->assertSame([
            'planner.plan',
            'gatherer.search',
            'planner.planFetches',
            'gatherer.fetch',
            'extractor',
            'synthesizer',
        ], $order);
    }

    public function test_plan_is_logged_after_planning(): void
    {
        $logged = null;

        Log::shouldReceive('debug')->andReturnUsing(function (string $msg, array $ctx) use (&$logged) {
            if ($msg === 'agent.plan') {
                $logged = ['message' => $msg, 'ctx' => $ctx];
            }
        });

        $planner = Mockery::mock(Planner::class);
        $planner->shouldReceive('plan')->once()->andReturnUsing(function (AgentContext $ctx) {
            $ctx->setPlan([['tool' => 'web_search', 'args' => ['query' => 'zinc']]]);
        });
        $planner->shouldReceive('planFetches')->once();

        $this->makeOrchestrator(planner: $planner)->run('Describe zinc');

        $this->assertNotNull($logged);
        $this->assertArrayHasKey('prompt', $logged['ctx']);
        $this->assertArrayHasKey('plan', $logged['ctx']);
    }

    // -------------------------------------------------------------------------
    // Cleanup
    // -------------------------------------------------------------------------

    public function test_temp_directory_is_deleted_after_successful_run(): void
    {
        $runId = null;

        $planner = Mockery::mock(Planner::class);
        $planner->shouldReceive('plan')->once()->andReturnUsing(function (AgentContext $ctx) use (&$runId) {
            $runId = $ctx->getRunId();
            Storage::makeDirectory("agent-runs/{$runId}");
            Storage::put("agent-runs/{$runId}/test.txt", 'data');
        });
        $planner->shouldReceive('planFetches')->once();

        $this->makeOrchestrator(planner: $planner)->run('prompt');

        $this->assertFalse(Storage::directoryExists("agent-runs/{$runId}"));
    }

    public function test_temp_directory_is_deleted_even_when_pipeline_throws(): void
    {
        $runId = null;

        $planner = Mockery::mock(Planner::class);
        $planner->shouldReceive('plan')->once()->andReturnUsing(function (AgentContext $ctx) use (&$runId) {
            $runId = $ctx->getRunId();
            Storage::makeDirectory("agent-runs/{$runId}");
            throw new LlmUnavailableException('Ollama down');
        });

        $gatherer    = Mockery::mock(Gatherer::class);
        $extractor   = Mockery::mock(Extractor::class);
        $synthesizer = Mockery::mock(Synthesizer::class);
        $llm         = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('unload')->once();

        try {
            (new AgentOrchestrator($planner, $gatherer, $extractor, $synthesizer, $llm))->run('prompt');
        } catch (LlmUnavailableException) {
            // expected
        }

        $this->assertFalse(Storage::directoryExists("agent-runs/{$runId}"));
    }

    // -------------------------------------------------------------------------
    // Error propagation
    // -------------------------------------------------------------------------

    public function test_llm_unavailable_exception_propagates(): void
    {
        $planner = Mockery::mock(Planner::class);
        $planner->shouldReceive('plan')->once()->andThrow(new LlmUnavailableException('Connection refused'));

        $gatherer    = Mockery::mock(Gatherer::class);
        $extractor   = Mockery::mock(Extractor::class);
        $synthesizer = Mockery::mock(Synthesizer::class);
        $llm         = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('unload')->once();

        $this->expectException(LlmUnavailableException::class);

        (new AgentOrchestrator($planner, $gatherer, $extractor, $synthesizer, $llm))->run('prompt');
    }
}
