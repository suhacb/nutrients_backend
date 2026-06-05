<?php

namespace Tests\Unit\Jobs;

use App\AI\Contracts\LlmClientContract;
use App\Jobs\ClassifyNutrientParent;
use App\Models\Nutrient;
use App\Models\SourceNutrient;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class ClassifyNutrientParentTest extends TestCase
{
    use RefreshDatabase;

    private Source $source;
    private Nutrient $parent;
    private Nutrient $nutrient;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        $this->source = Source::factory()->create(['slug' => 'usda', 'name' => 'USDA']);

        // A canonical hierarchy node (valid parent target)
        $this->parent = Nutrient::withoutEvents(
            fn () => Nutrient::factory()->create(['name' => 'Trace Minerals', 'parent_id' => null])
        );

        // An imported, unclassified nutrient
        $this->nutrient = Nutrient::withoutEvents(
            fn () => Nutrient::factory()->create(['name' => 'Boron', 'parent_id' => null])
        );
        SourceNutrient::create([
            'source_id'   => $this->source->id,
            'external_id' => '2049',
            'name'        => 'Boron',
            'nutrient_id' => $this->nutrient->id,
            'resolved_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockLlm(string $json): void
    {
        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')->once()->andReturn($json);
        $this->app->instance(LlmClientContract::class, $llm);
    }

    // -------------------------------------------------------------------------
    // handle()
    // -------------------------------------------------------------------------

    public function test_assigns_parent_id_when_confidence_meets_threshold(): void
    {
        $this->mockLlm(json_encode(['parent_id' => $this->parent->id, 'confidence' => 90, 'reasoning' => 'It is a trace mineral.']));

        (new ClassifyNutrientParent($this->nutrient))->handle(app(LlmClientContract::class));

        $this->assertSame($this->parent->id, $this->nutrient->fresh()->parent_id);
    }

    public function test_does_not_assign_parent_when_confidence_is_below_threshold(): void
    {
        $this->mockLlm(json_encode(['parent_id' => $this->parent->id, 'confidence' => 79, 'reasoning' => 'Uncertain.']));

        (new ClassifyNutrientParent($this->nutrient))->handle(app(LlmClientContract::class));

        $this->assertNull($this->nutrient->fresh()->parent_id);
    }

    public function test_assigns_parent_at_exactly_80_confidence(): void
    {
        $this->mockLlm(json_encode(['parent_id' => $this->parent->id, 'confidence' => 80, 'reasoning' => 'Likely a trace mineral.']));

        (new ClassifyNutrientParent($this->nutrient))->handle(app(LlmClientContract::class));

        $this->assertSame($this->parent->id, $this->nutrient->fresh()->parent_id);
    }

    public function test_rejects_parent_id_not_in_canonical_hierarchy(): void
    {
        $invalidId = 999999;
        $this->mockLlm(json_encode(['parent_id' => $invalidId, 'confidence' => 95, 'reasoning' => 'Hallucinated.']));

        (new ClassifyNutrientParent($this->nutrient))->handle(app(LlmClientContract::class));

        $this->assertNull($this->nutrient->fresh()->parent_id);
    }

    public function test_prompt_includes_nutrient_name(): void
    {
        $captured = null;
        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')->once()->andReturnUsing(function (array $messages) use (&$captured) {
            $captured = $messages;
            return json_encode(['parent_id' => $this->parent->id, 'confidence' => 90, 'reasoning' => 'ok']);
        });
        $this->app->instance(LlmClientContract::class, $llm);

        (new ClassifyNutrientParent($this->nutrient))->handle(app(LlmClientContract::class));

        $userContent = collect($captured)->firstWhere('role', 'user')['content'] ?? '';
        $this->assertStringContainsString('Boron', $userContent);
    }

    public function test_does_not_fire_model_events_on_update(): void
    {
        $this->mockLlm(json_encode(['parent_id' => $this->parent->id, 'confidence' => 90, 'reasoning' => 'ok']));

        Bus::assertNotDispatched(\App\Jobs\SyncNutrientToSearch::class);

        (new ClassifyNutrientParent($this->nutrient))->handle(app(LlmClientContract::class));

        Bus::assertNotDispatched(\App\Jobs\SyncNutrientToSearch::class);
    }

    // -------------------------------------------------------------------------
    // Queue configuration
    // -------------------------------------------------------------------------

    public function test_timeout_reads_from_config(): void
    {
        config(['ai.ollama.timeout' => 600]);
        $this->assertSame(600, (new ClassifyNutrientParent($this->nutrient))->timeout);
    }

    public function test_timeout_defaults_to_300(): void
    {
        config(['ai.ollama.timeout' => null]);
        $this->assertSame(300, (new ClassifyNutrientParent($this->nutrient))->timeout);
    }

    public function test_tries_is_two(): void
    {
        $this->assertSame(2, (new ClassifyNutrientParent($this->nutrient))->tries);
    }
}
