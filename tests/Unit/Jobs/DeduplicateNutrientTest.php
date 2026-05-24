<?php

namespace Tests\Unit\Jobs;

use App\AI\Contracts\LlmClientContract;
use App\AI\Tools\WebSearchTool;
use App\Jobs\DeduplicateNutrient;
use App\Models\Nutrient;
use App\Models\NutrientMappingReview;
use App\Models\NutrientSourcePivot;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\MakesUnit;
use Tests\TestCase;

class DeduplicateNutrientTest extends TestCase
{
    use RefreshDatabase, MakesUnit;

    private Source $source;
    private Nutrient $canonical;
    private Nutrient $imported;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        $this->source = Source::factory()->create(['slug' => 'usda', 'name' => 'USDA']);

        $this->canonical = Nutrient::withoutEvents(
            fn () => Nutrient::factory()->create(['name' => 'Vitamin D', 'is_canonical' => true])
        );

        $this->imported = Nutrient::withoutEvents(
            fn () => Nutrient::factory()->create(['name' => 'Vitamin D (D2+D3)'])
        );
        NutrientSourcePivot::create([
            'nutrient_id' => $this->imported->id,
            'source_id'   => $this->source->id,
            'external_id' => '1114',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockLlm(string $json): LlmClientContract
    {
        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')->andReturn($json);
        $this->app->instance(LlmClientContract::class, $llm);
        return $llm;
    }

    private function mockSearch(array $results = []): WebSearchTool
    {
        $mock = Mockery::mock(WebSearchTool::class);
        $mock->shouldReceive('run')->andReturn($results);
        return $mock;
    }

    private function handle(Nutrient $nutrient, ?WebSearchTool $search = null): void
    {
        (new DeduplicateNutrient($nutrient))->handle(
            app(LlmClientContract::class),
            $search ?? $this->mockSearch(),
        );
    }

    // -------------------------------------------------------------------------
    // Auto-merge (confidence >= 95)
    // -------------------------------------------------------------------------

    public function test_auto_merges_when_confidence_is_95(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'action' => 'merge', 'confidence' => 95, 'reasoning' => 'Same substance.']));

        $this->handle($this->imported);

        $this->assertDatabaseMissing('nutrients', ['id' => $this->imported->id]);
    }

    public function test_auto_merges_when_confidence_exceeds_95(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'action' => 'merge', 'confidence' => 99, 'reasoning' => 'Identical.']));

        $this->handle($this->imported);

        $this->assertDatabaseMissing('nutrients', ['id' => $this->imported->id, 'deleted_at' => null]);
    }

    public function test_defaults_to_merge_action_when_action_field_absent(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 95, 'reasoning' => 'Same substance.']));

        $this->handle($this->imported);

        $this->assertDatabaseMissing('nutrients', ['id' => $this->imported->id]);
    }

    public function test_repivots_source_mapping_to_canonical_on_merge(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'action' => 'merge', 'confidence' => 95, 'reasoning' => 'Same.']));

        $this->handle($this->imported);

        $this->assertDatabaseHas('nutrient_source_mappings', [
            'nutrient_id' => $this->canonical->id,
            'external_id' => '1114',
        ]);
        $this->assertDatabaseMissing('nutrient_source_mappings', [
            'nutrient_id' => $this->imported->id,
        ]);
    }

    public function test_repivots_ingredient_nutrient_to_canonical_on_merge(): void
    {
        $ingredient = \App\Models\Ingredient::factory()->create();
        $unit       = $this->makeUnit();
        DB::table('ingredient_nutrient')->insert([
            'ingredient_id'  => $ingredient->id,
            'nutrient_id'    => $this->imported->id,
            'amount'         => 5.0,
            'amount_unit_id' => $unit->id,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'action' => 'merge', 'confidence' => 95, 'reasoning' => 'Same.']));

        $this->handle($this->imported);

        $this->assertDatabaseHas('ingredient_nutrient', [
            'ingredient_id' => $ingredient->id,
            'nutrient_id'   => $this->canonical->id,
            'amount'        => 5.0,
        ]);
        $this->assertDatabaseMissing('ingredient_nutrient', [
            'nutrient_id' => $this->imported->id,
        ]);
    }

    public function test_sums_amounts_when_canonical_already_has_same_ingredient_and_unit_on_merge(): void
    {
        $ingredient = \App\Models\Ingredient::factory()->create();
        $unit       = $this->makeUnit();

        // Canonical already has this ingredient+unit (from a previous merge)
        DB::table('ingredient_nutrient')->insert([
            'ingredient_id'  => $ingredient->id,
            'nutrient_id'    => $this->canonical->id,
            'amount'         => 3.0,
            'amount_unit_id' => $unit->id,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Imported also has this ingredient+unit — would conflict on re-point
        DB::table('ingredient_nutrient')->insert([
            'ingredient_id'  => $ingredient->id,
            'nutrient_id'    => $this->imported->id,
            'amount'         => 5.0,
            'amount_unit_id' => $unit->id,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'action' => 'merge', 'confidence' => 95, 'reasoning' => 'Same.']));

        $this->handle($this->imported);

        // Amounts summed into canonical row; imported row removed
        $this->assertDatabaseHas('ingredient_nutrient', [
            'ingredient_id' => $ingredient->id,
            'nutrient_id'   => $this->canonical->id,
            'amount'        => 8.0,
        ]);
        $this->assertDatabaseMissing('ingredient_nutrient', [
            'nutrient_id' => $this->imported->id,
        ]);
    }

    public function test_does_not_create_review_on_auto_merge(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'action' => 'merge', 'confidence' => 95, 'reasoning' => 'Same.']));

        $this->handle($this->imported);

        $this->assertDatabaseCount('nutrient_mapping_reviews', 0);
    }

    // -------------------------------------------------------------------------
    // Parent classification (action = parent, confidence >= 95)
    // -------------------------------------------------------------------------

    public function test_sets_parent_id_when_action_is_parent_and_confidence_is_95(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'action' => 'parent', 'confidence' => 95, 'reasoning' => 'Specific form.']));

        $this->handle($this->imported);

        $this->assertDatabaseHas('nutrients', [
            'id'        => $this->imported->id,
            'parent_id' => $this->canonical->id,
        ]);
    }

    public function test_does_not_delete_nutrient_when_classified_as_child(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'action' => 'parent', 'confidence' => 95, 'reasoning' => 'Specific form.']));

        $this->handle($this->imported);

        $this->assertDatabaseHas('nutrients', ['id' => $this->imported->id, 'deleted_at' => null]);
    }

    public function test_keeps_source_mappings_when_classified_as_child(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'action' => 'parent', 'confidence' => 95, 'reasoning' => 'Specific form.']));

        $this->handle($this->imported);

        $this->assertDatabaseHas('nutrient_source_mappings', [
            'nutrient_id' => $this->imported->id,
            'external_id' => '1114',
        ]);
    }

    public function test_does_not_create_review_when_classified_as_child(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'action' => 'parent', 'confidence' => 95, 'reasoning' => 'Specific form.']));

        $this->handle($this->imported);

        $this->assertDatabaseCount('nutrient_mapping_reviews', 0);
    }

    public function test_review_stores_parent_decision_type_when_low_confidence(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'action' => 'parent', 'confidence' => 70, 'reasoning' => 'Probably a subtype.']));

        $this->handle($this->imported);

        $this->assertDatabaseHas('nutrient_mapping_reviews', [
            'nutrient_id'            => $this->imported->id,
            'suggested_canonical_id' => $this->canonical->id,
            'decision_type'          => 'parent',
            'confidence'             => 70,
            'status'                 => 'pending',
        ]);
    }

    // -------------------------------------------------------------------------
    // Review queue (confidence < 95)
    // -------------------------------------------------------------------------

    public function test_creates_review_when_confidence_is_below_95(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'action' => 'merge', 'confidence' => 80, 'reasoning' => 'Probably.']));

        $this->handle($this->imported);

        $this->assertDatabaseHas('nutrient_mapping_reviews', [
            'nutrient_id'            => $this->imported->id,
            'suggested_canonical_id' => $this->canonical->id,
            'confidence'             => 80,
            'status'                 => 'pending',
        ]);
    }

    public function test_review_stores_reasoning(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'action' => 'merge', 'confidence' => 70, 'reasoning' => 'Unclear form.']));

        $this->handle($this->imported);

        $this->assertSame('Unclear form.', NutrientMappingReview::first()->reasoning);
    }

    public function test_does_not_delete_nutrient_when_review_created(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'action' => 'merge', 'confidence' => 80, 'reasoning' => 'Maybe.']));

        $this->handle($this->imported);

        $this->assertDatabaseHas('nutrients', ['id' => $this->imported->id, 'deleted_at' => null]);
    }

    // -------------------------------------------------------------------------
    // No match
    // -------------------------------------------------------------------------

    public function test_no_action_when_llm_returns_no_match(): void
    {
        $this->mockLlm(json_encode(['match' => null, 'confidence' => 100, 'reasoning' => 'Novel nutrient.']));

        $this->handle($this->imported);

        $this->assertDatabaseHas('nutrients', ['id' => $this->imported->id, 'deleted_at' => null]);
        $this->assertDatabaseCount('nutrient_mapping_reviews', 0);
    }

    // -------------------------------------------------------------------------
    // Idempotency / guard
    // -------------------------------------------------------------------------

    public function test_skips_nutrient_that_no_longer_has_source_mappings(): void
    {
        NutrientSourcePivot::where('nutrient_id', $this->imported->id)->delete();

        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldNotReceive('chat');
        $this->app->instance(LlmClientContract::class, $llm);

        $this->handle($this->imported);

        $this->assertDatabaseCount('nutrient_mapping_reviews', 0);
    }

    public function test_does_not_create_duplicate_review_on_rerun(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 80, 'reasoning' => 'Probably.']));
        $this->handle($this->imported);

        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldNotReceive('chat');
        $this->app->instance(LlmClientContract::class, $llm);

        $this->handle($this->imported);

        $this->assertDatabaseCount('nutrient_mapping_reviews', 1);
    }

    // -------------------------------------------------------------------------
    // Web search
    // -------------------------------------------------------------------------

    public function test_web_search_is_always_triggered(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 95, 'reasoning' => 'Same.']));

        $search = Mockery::mock(WebSearchTool::class);
        $search->shouldReceive('run')->once()->andReturn([]);
        $this->addToAssertionCount(1);

        $this->handle($this->imported, $search);
    }

    public function test_web_search_uses_nutrient_explained_query(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 95, 'reasoning' => 'Same.']));

        $search = Mockery::mock(WebSearchTool::class);
        $search->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $args) => str_contains($args['query'] ?? '', 'nutrient explained'))
            ->andReturn([]);
        $this->addToAssertionCount(1);

        $this->handle($this->imported, $search);
    }

    public function test_web_search_requests_five_results(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 95, 'reasoning' => 'Same.']));

        $search = Mockery::mock(WebSearchTool::class);
        $search->shouldReceive('run')
            ->once()
            ->withArgs(fn (array $args) => ($args['limit'] ?? 0) === 5)
            ->andReturn([]);
        $this->addToAssertionCount(1);

        $this->handle($this->imported, $search);
    }

    public function test_web_context_is_included_in_classify_call(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 95, 'reasoning' => 'Web confirms.']));

        $search = $this->mockSearch([
            ['url' => 'https://example.com', 'title' => 'Vitamin D', 'snippet' => 'D2 and D3 are both Vitamin D.'],
        ]);

        $this->handle($this->imported, $search);

        $this->assertDatabaseMissing('nutrients', ['id' => $this->imported->id]);
    }

    public function test_creates_review_when_confidence_below_98_even_with_web_context(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 75, 'reasoning' => 'Possibly the same.']));

        $search = $this->mockSearch([
            ['url' => 'https://example.com', 'title' => 'Vitamin D', 'snippet' => 'Related but distinct.'],
        ]);

        $this->handle($this->imported, $search);

        $this->assertDatabaseHas('nutrient_mapping_reviews', [
            'nutrient_id'            => $this->imported->id,
            'suggested_canonical_id' => $this->canonical->id,
            'confidence'             => 75,
            'status'                 => 'pending',
        ]);
    }

    public function test_web_search_failure_queues_for_review_without_calling_llm(): void
    {
        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldNotReceive('chat');
        $this->app->instance(LlmClientContract::class, $llm);

        $search = Mockery::mock(WebSearchTool::class);
        $search->shouldReceive('run')->andThrow(new \RuntimeException('SearXNG unavailable'));

        $this->handle($this->imported, $search);

        $this->assertDatabaseHas('nutrient_mapping_reviews', [
            'nutrient_id'            => $this->imported->id,
            'suggested_canonical_id' => null,
            'confidence'             => 0,
            'status'                 => 'pending',
        ]);
    }

    // -------------------------------------------------------------------------
    // Queue configuration
    // -------------------------------------------------------------------------

    public function test_timeout_reads_from_config(): void
    {
        $this->assertSame((int) config('ai.ollama.timeout'), (new DeduplicateNutrient($this->imported))->timeout);
    }

    public function test_tries_is_two(): void
    {
        $this->assertSame(2, (new DeduplicateNutrient($this->imported))->tries);
    }
}
