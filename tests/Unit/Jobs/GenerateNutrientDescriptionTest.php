<?php

namespace Tests\Unit\Jobs;

use App\AI\AgentOrchestrator;
use App\Jobs\GenerateNutrientDescription;
use App\Models\Nutrient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class GenerateNutrientDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private Nutrient $nutrient;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $this->nutrient = Nutrient::withoutEvents(fn () => Nutrient::factory()->create(['description' => null]));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // handle()
    // -------------------------------------------------------------------------

    public function test_handle_saves_generated_description_to_nutrient(): void
    {
        $orchestrator = Mockery::mock(AgentOrchestrator::class);
        $orchestrator->shouldReceive('run')->once()->andReturn('Zinc is an essential trace mineral.');

        (new GenerateNutrientDescription($this->nutrient))->handle($orchestrator);

        $this->assertSame('Zinc is an essential trace mineral.', $this->nutrient->fresh()->description);
    }

    public function test_handle_prompt_contains_nutrient_name(): void
    {
        $capturedPrompt = null;
        $orchestrator   = Mockery::mock(AgentOrchestrator::class);
        $orchestrator->shouldReceive('run')->once()->andReturnUsing(function (string $prompt) use (&$capturedPrompt) {
            $capturedPrompt = $prompt;
            return 'description';
        });

        (new GenerateNutrientDescription($this->nutrient))->handle($orchestrator);

        $this->assertStringContainsString($this->nutrient->name, $capturedPrompt);
    }

    // -------------------------------------------------------------------------
    // failed()
    // -------------------------------------------------------------------------

    public function test_failed_logs_error_with_nutrient_id_and_name(): void
    {
        $logged = null;

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->once()->andReturnUsing(function (string $msg, array $ctx) use (&$logged) {
            $logged = ['message' => $msg, 'ctx' => $ctx];
        });

        (new GenerateNutrientDescription($this->nutrient))->failed(new \Exception('LLM unavailable'));

        $this->assertSame('generate_description.failed', $logged['message']);
        $this->assertSame($this->nutrient->id, $logged['ctx']['nutrient_id']);
        $this->assertSame($this->nutrient->name, $logged['ctx']['name']);
        $this->assertStringContainsString('LLM unavailable', $logged['ctx']['error']);
    }

    // -------------------------------------------------------------------------
    // Queue configuration
    // -------------------------------------------------------------------------

    public function test_job_dispatches_to_nutrients_queue(): void
    {
        GenerateNutrientDescription::dispatch($this->nutrient)->onQueue('nutrients');

        Bus::assertDispatched(GenerateNutrientDescription::class, function (GenerateNutrientDescription $job) {
            return $job->queue === 'nutrients';
        });
    }

    public function test_timeout_is_600_seconds(): void
    {
        $this->assertSame(600, (new GenerateNutrientDescription($this->nutrient))->timeout);
    }

    public function test_tries_is_one(): void
    {
        $this->assertSame(1, (new GenerateNutrientDescription($this->nutrient))->tries);
    }
}
