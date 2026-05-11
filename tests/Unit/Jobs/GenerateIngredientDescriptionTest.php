<?php

namespace Tests\Unit\Jobs;

use App\AI\AgentOrchestrator;
use App\Jobs\GenerateIngredientDescription;
use App\Models\Brand;
use App\Models\Ingredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class GenerateIngredientDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private Ingredient $ingredient;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $this->ingredient = Ingredient::withoutEvents(fn () => Ingredient::factory()->create(['description' => null]));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // handle()
    // -------------------------------------------------------------------------

    public function test_handle_saves_generated_description_to_ingredient(): void
    {
        $orchestrator = Mockery::mock(AgentOrchestrator::class);
        $orchestrator->shouldReceive('run')->once()->andReturn('Olive oil is a staple of the Mediterranean diet.');

        (new GenerateIngredientDescription($this->ingredient))->handle($orchestrator);

        $this->assertSame('Olive oil is a staple of the Mediterranean diet.', $this->ingredient->fresh()->description);
    }

    public function test_handle_prompt_contains_ingredient_name(): void
    {
        $capturedPrompt = null;
        $orchestrator   = Mockery::mock(AgentOrchestrator::class);
        $orchestrator->shouldReceive('run')->once()->andReturnUsing(function (string $prompt) use (&$capturedPrompt) {
            $capturedPrompt = $prompt;
            return 'description';
        });

        (new GenerateIngredientDescription($this->ingredient))->handle($orchestrator);

        $this->assertStringContainsString($this->ingredient->name, $capturedPrompt);
    }

    public function test_handle_prompt_covers_all_categories(): void
    {
        $capturedPrompt = null;
        $orchestrator   = Mockery::mock(AgentOrchestrator::class);
        $orchestrator->shouldReceive('run')->once()->andReturnUsing(function (string $prompt) use (&$capturedPrompt) {
            $capturedPrompt = $prompt;
            return 'description';
        });

        (new GenerateIngredientDescription($this->ingredient))->handle($orchestrator);

        foreach (config('ai.ingredient_description.categories') as $category) {
            $this->assertStringContainsStringIgnoringCase($category, $capturedPrompt);
        }
    }

    public function test_handle_prompt_mentions_all_diets(): void
    {
        $capturedPrompt = null;
        $orchestrator   = Mockery::mock(AgentOrchestrator::class);
        $orchestrator->shouldReceive('run')->once()->andReturnUsing(function (string $prompt) use (&$capturedPrompt) {
            $capturedPrompt = $prompt;
            return 'description';
        });

        (new GenerateIngredientDescription($this->ingredient))->handle($orchestrator);

        foreach (config('ai.ingredient_description.diets') as $diet) {
            $this->assertStringContainsStringIgnoringCase($diet, $capturedPrompt);
        }
    }

    public function test_handle_prompt_includes_brand_when_present(): void
    {
        $brand      = Brand::factory()->create(['name' => 'Mars Snackfood US']);
        $ingredient = Ingredient::withoutEvents(fn () => Ingredient::factory()->create([
            'description' => null,
            'brand_id'    => $brand->id,
        ]));

        $capturedPrompt = null;
        $orchestrator   = Mockery::mock(AgentOrchestrator::class);
        $orchestrator->shouldReceive('run')->once()->andReturnUsing(function (string $prompt) use (&$capturedPrompt) {
            $capturedPrompt = $prompt;
            return 'description';
        });

        (new GenerateIngredientDescription($ingredient))->handle($orchestrator);

        $this->assertStringContainsString('Mars Snackfood US', $capturedPrompt);
    }

    public function test_handle_prompt_omits_brand_when_not_present(): void
    {
        $capturedPrompt = null;
        $orchestrator   = Mockery::mock(AgentOrchestrator::class);
        $orchestrator->shouldReceive('run')->once()->andReturnUsing(function (string $prompt) use (&$capturedPrompt) {
            $capturedPrompt = $prompt;
            return 'description';
        });

        (new GenerateIngredientDescription($this->ingredient))->handle($orchestrator);

        $this->assertStringNotContainsString('brand:', $capturedPrompt);
    }

    // -------------------------------------------------------------------------
    // failed()
    // -------------------------------------------------------------------------

    public function test_failed_logs_error_with_ingredient_id_and_name(): void
    {
        $logged = null;

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->once()->andReturnUsing(function (string $msg, array $ctx) use (&$logged) {
            $logged = ['message' => $msg, 'ctx' => $ctx];
        });

        (new GenerateIngredientDescription($this->ingredient))->failed(new \Exception('LLM unavailable'));

        $this->assertSame('generate_ingredient_description.failed', $logged['message']);
        $this->assertSame($this->ingredient->id, $logged['ctx']['ingredient_id']);
        $this->assertSame($this->ingredient->name, $logged['ctx']['name']);
        $this->assertStringContainsString('LLM unavailable', $logged['ctx']['error']);
    }

    // -------------------------------------------------------------------------
    // Queue configuration
    // -------------------------------------------------------------------------

    public function test_job_dispatches_to_ingredients_queue(): void
    {
        GenerateIngredientDescription::dispatch($this->ingredient)->onQueue('ingredients');

        Bus::assertDispatched(GenerateIngredientDescription::class, function (GenerateIngredientDescription $job) {
            return $job->queue === 'ingredients';
        });
    }

    public function test_timeout_is_600_seconds(): void
    {
        $this->assertSame(600, (new GenerateIngredientDescription($this->ingredient))->timeout);
    }

    public function test_tries_is_one(): void
    {
        $this->assertSame(1, (new GenerateIngredientDescription($this->ingredient))->tries);
    }
}
