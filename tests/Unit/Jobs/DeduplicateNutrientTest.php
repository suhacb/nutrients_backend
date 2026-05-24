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
            fn () => Nutrient::factory()->create(['name' => 'Vitamin D'])
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
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 95, 'reasoning' => 'Same substance.']));

        $this->handle($this->imported);

        $this->assertDatabaseMissing('nutrients', ['id' => $this->imported->id]);
    }

    public function test_auto_merges_when_confidence_exceeds_95(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 99, 'reasoning' => 'Identical.']));

        $this->handle($this->imported);

        $this->assertDatabaseMissing('nutrients', ['id' => $this->imported->id, 'deleted_at' => null]);
    }

    public function test_repivots_source_mapping_to_canonical_on_merge(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 97, 'reasoning' => 'Same.']));

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

        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 97, 'reasoning' => 'Same.']));

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

    public function test_does_not_create_review_on_auto_merge(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 97, 'reasoning' => 'Same.']));

        $this->handle($this->imported);

        $this->assertDatabaseCount('nutrient_mapping_reviews', 0);
    }

    // -------------------------------------------------------------------------
    // Review queue (confidence < 95)
    // -------------------------------------------------------------------------

    public function test_creates_review_when_confidence_is_below_95(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 80, 'reasoning' => 'Probably.']));

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
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 70, 'reasoning' => 'Unclear form.']));

        $this->handle($this->imported);

        $this->assertSame('Unclear form.', NutrientMappingReview::first()->reasoning);
    }

    public function test_does_not_delete_nutrient_when_review_created(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 80, 'reasoning' => 'Maybe.']));

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
    // Web search fallback
    // -------------------------------------------------------------------------

    public function test_web_search_is_triggered_when_first_confidence_is_below_threshold(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 80, 'reasoning' => 'Probably.']));

        $search = Mockery::mock(WebSearchTool::class);
        $search->shouldReceive('run')->once()->andReturn([]);
        $this->addToAssertionCount(1);

        $this->handle($this->imported, $search);
    }

    public function test_web_search_is_not_triggered_when_first_confidence_meets_threshold(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 95, 'reasoning' => 'Same.']));

        $search = Mockery::mock(WebSearchTool::class);
        $search->shouldNotReceive('run');
        $this->addToAssertionCount(1);

        $this->handle($this->imported, $search);
    }

    public function test_auto_merges_when_second_pass_confidence_meets_threshold(): void
    {
        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')->andReturn(
            json_encode(['match' => 'Vitamin D', 'confidence' => 70, 'reasoning' => 'Uncertain.']),
            json_encode(['match' => 'Vitamin D', 'confidence' => 97, 'reasoning' => 'Web confirms same substance.']),
        );
        $this->app->instance(LlmClientContract::class, $llm);

        $search = $this->mockSearch([
            ['url' => 'https://example.com', 'title' => 'Vitamin D', 'snippet' => 'D2 and D3 are both Vitamin D.'],
        ]);

        $this->handle($this->imported, $search);

        $this->assertDatabaseMissing('nutrients', ['id' => $this->imported->id]);
        $this->assertDatabaseCount('nutrient_mapping_reviews', 0);
    }

    public function test_creates_review_when_second_pass_confidence_is_still_below_threshold(): void
    {
        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')->andReturn(
            json_encode(['match' => 'Vitamin D', 'confidence' => 60, 'reasoning' => 'Uncertain.']),
            json_encode(['match' => 'Vitamin D', 'confidence' => 75, 'reasoning' => 'Possibly the same.']),
        );
        $this->app->instance(LlmClientContract::class, $llm);

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

    public function test_no_action_when_second_pass_also_returns_no_match(): void
    {
        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')->andReturn(
            json_encode(['match' => null, 'confidence' => 0, 'reasoning' => 'Novel nutrient.']),
            json_encode(['match' => null, 'confidence' => 0, 'reasoning' => 'Still no match.']),
        );
        $this->app->instance(LlmClientContract::class, $llm);

        $search = $this->mockSearch([
            ['url' => 'https://example.com', 'title' => 'Vitamin D', 'snippet' => 'Context.'],
        ]);

        $this->handle($this->imported, $search);

        $this->assertDatabaseHas('nutrients', ['id' => $this->imported->id, 'deleted_at' => null]);
        $this->assertDatabaseCount('nutrient_mapping_reviews', 0);
    }

    public function test_web_search_failure_falls_back_to_first_pass_result(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 80, 'reasoning' => 'Probably.']));

        $search = Mockery::mock(WebSearchTool::class);
        $search->shouldReceive('run')->andThrow(new \RuntimeException('SearXNG unavailable'));

        $this->handle($this->imported, $search);

        $this->assertDatabaseHas('nutrient_mapping_reviews', [
            'nutrient_id' => $this->imported->id,
            'confidence'  => 80,
            'status'      => 'pending',
        ]);
    }

    // -------------------------------------------------------------------------
    // Queue configuration
    // -------------------------------------------------------------------------

    public function test_timeout_is_120_seconds(): void
    {
        $this->assertSame(120, (new DeduplicateNutrient($this->imported))->timeout);
    }

    public function test_tries_is_two(): void
    {
        $this->assertSame(2, (new DeduplicateNutrient($this->imported))->tries);
    }
}
