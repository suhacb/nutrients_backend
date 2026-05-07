<?php

namespace Tests\Feature\Console;

use App\AI\Contracts\LlmClientContract;
use App\Models\Nutrient;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ClassifyNutrientParentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Source::firstOrCreate(['slug' => 'system'], ['name' => 'System']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function nutrient(array $attrs = []): Nutrient
    {
        return Nutrient::withoutEvents(fn () => Nutrient::factory()->create($attrs));
    }

    private function hierarchyNutrient(array $attrs = []): Nutrient
    {
        $systemSourceId = Source::where('slug', 'system')->value('id');
        $nutrient = $this->nutrient(array_merge(['source_id' => $systemSourceId], $attrs));
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
            Nutrient::factory()->create(['parent_id' => null])->delete();
        });

        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldNotReceive('chat');
        $this->app->instance(LlmClientContract::class, $llm);

        $this->artisan('nutrients:classify-parents')->assertSuccessful();
    }
}
