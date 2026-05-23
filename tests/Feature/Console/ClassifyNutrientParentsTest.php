<?php

namespace Tests\Feature\Console;

use App\AI\Contracts\LlmClientContract;
use App\Models\Nutrient;
use App\Models\NutrientSourcePivot;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ClassifyNutrientParentsTest extends TestCase
{
    use RefreshDatabase;

    protected Source $externalSource;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Source::firstOrCreate(['slug' => 'system'], ['name' => 'System']);
        $this->externalSource = Source::factory()->create(['slug' => 'usda', 'name' => 'USDA']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** Creates an external (non-canonical) nutrient with a source mapping — eligible for classification. */
    private function nutrient(array $attrs = []): Nutrient
    {
        $nutrient = Nutrient::withoutEvents(fn () => Nutrient::factory()->create($attrs));
        NutrientSourcePivot::create([
            'nutrient_id' => $nutrient->id,
            'source_id'   => $this->externalSource->id,
            'external_id' => (string) $nutrient->id,
        ]);
        return $nutrient;
    }

    /** Creates a canonical hierarchy nutrient (no source mapping) — used as a valid parent. */
    private function hierarchyNutrient(array $attrs = []): Nutrient
    {
        $nutrient = Nutrient::withoutEvents(fn () => Nutrient::factory()->create($attrs));
        Nutrient::withoutEvents(fn () => $nutrient->update(['parent_id' => $nutrient->id]));
        return $nutrient;
    }

    /** Mock the LLM to return {"parent_id": $parentId} for every chat() call. */
    private function mockLlm(int $parentId): void
    {
        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')->andReturn(json_encode(['parent_id' => $parentId]));
        $this->app->instance(LlmClientContract::class, $llm);
    }

    // -------------------------------------------------------------------------
    // Default behaviour
    // -------------------------------------------------------------------------

    public function test_assigns_parent_id_and_persists(): void
    {
        $parent = $this->hierarchyNutrient(['name' => 'Vitamins']);
        $child  = $this->nutrient(['name' => 'Vitamin C']);

        $this->mockLlm($parent->id);

        $this->artisan('nutrients:classify-parents')->assertSuccessful();

        $this->assertSame($parent->id, $child->fresh()->parent_id);
    }

    public function test_outputs_classification_count(): void
    {
        $parent = $this->hierarchyNutrient();
        $child  = $this->nutrient();

        $this->mockLlm($parent->id);

        $this->artisan('nutrients:classify-parents')
            ->expectsOutputToContain('1')
            ->assertSuccessful();
    }

    public function test_does_nothing_when_no_unclassified_nutrients(): void
    {
        $nutrient = $this->nutrient();
        Nutrient::withoutEvents(fn () => $nutrient->update(['parent_id' => $nutrient->id]));

        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldNotReceive('chat');
        $this->app->instance(LlmClientContract::class, $llm);

        $this->artisan('nutrients:classify-parents')->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function test_rejects_invalid_parent_id(): void
    {
        $child = $this->nutrient();

        $this->mockLlm(99999);

        $this->artisan('nutrients:classify-parents')->assertSuccessful();

        $this->assertNull($child->fresh()->parent_id);
    }

    // -------------------------------------------------------------------------
    // --dry-run flag
    // -------------------------------------------------------------------------

    public function test_dry_run_does_not_persist_changes(): void
    {
        $parent = $this->hierarchyNutrient();
        $child  = $this->nutrient();

        $this->mockLlm($parent->id);

        $this->artisan('nutrients:classify-parents --dry-run')->assertSuccessful();

        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_dry_run_outputs_nutrient_and_proposed_parent_names(): void
    {
        $parent = $this->hierarchyNutrient(['name' => 'Vitamins']);
        $child  = $this->nutrient(['name' => 'Vitamin C']);

        $this->mockLlm($parent->id);

        // Each name must appear in a separate doWrite call to satisfy both
        // Mockery expectations independently — see BeautifyIngredientNames for context.
        $this->artisan('nutrients:classify-parents --dry-run')
            ->expectsOutputToContain('Vitamin C')
            ->expectsOutputToContain('Vitamins')
            ->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // --id flag
    // -------------------------------------------------------------------------

    public function test_id_flag_processes_only_specified_nutrients(): void
    {
        $parent = $this->hierarchyNutrient();
        $a      = $this->nutrient();
        $b      = $this->nutrient();
        $c      = $this->nutrient();

        $this->mockLlm($parent->id);

        $this->artisan("nutrients:classify-parents --id={$a->id} --id={$b->id}")->assertSuccessful();

        $this->assertSame($parent->id, $a->fresh()->parent_id);
        $this->assertSame($parent->id, $b->fresh()->parent_id);
        $this->assertNull($c->fresh()->parent_id);
    }

    // -------------------------------------------------------------------------
    // Soft deletes
    // -------------------------------------------------------------------------

    public function test_skips_soft_deleted_nutrients(): void
    {
        Nutrient::withoutEvents(function () {
            $n = Nutrient::factory()->create(['parent_id' => null]);
            NutrientSourcePivot::create([
                'nutrient_id' => $n->id,
                'source_id'   => $this->externalSource->id,
                'external_id' => (string) $n->id,
            ]);
            $n->delete();
        });

        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldNotReceive('chat');
        $this->app->instance(LlmClientContract::class, $llm);

        $this->artisan('nutrients:classify-parents')->assertSuccessful();
    }
}
