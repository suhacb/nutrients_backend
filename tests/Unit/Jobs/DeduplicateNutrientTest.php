<?php

namespace Tests\Unit\Jobs;

use App\AI\Contracts\LlmClientContract;
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

        // Canonical nutrient: no source mappings
        $this->canonical = Nutrient::withoutEvents(
            fn () => Nutrient::factory()->create(['name' => 'Vitamin D'])
        );

        // Imported nutrient: has a source mapping
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
        $llm->shouldReceive('chat')->once()->andReturn($json);
        $this->app->instance(LlmClientContract::class, $llm);
        return $llm;
    }

    // -------------------------------------------------------------------------
    // Auto-merge (confidence >= 95)
    // -------------------------------------------------------------------------

    public function test_auto_merges_when_confidence_is_95(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 95, 'reasoning' => 'Same substance.']));

        (new DeduplicateNutrient($this->imported))->handle(app(LlmClientContract::class));

        $this->assertDatabaseMissing('nutrients', ['id' => $this->imported->id]);
    }

    public function test_auto_merges_when_confidence_exceeds_95(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 99, 'reasoning' => 'Identical.']));

        (new DeduplicateNutrient($this->imported))->handle(app(LlmClientContract::class));

        $this->assertDatabaseMissing('nutrients', ['id' => $this->imported->id, 'deleted_at' => null]);
    }

    public function test_repivots_source_mapping_to_canonical_on_merge(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 97, 'reasoning' => 'Same.']));

        (new DeduplicateNutrient($this->imported))->handle(app(LlmClientContract::class));

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
            'ingredient_id'   => $ingredient->id,
            'nutrient_id'     => $this->imported->id,
            'amount'          => 5.0,
            'amount_unit_id'  => $unit->id,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 97, 'reasoning' => 'Same.']));

        (new DeduplicateNutrient($this->imported))->handle(app(LlmClientContract::class));

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

        (new DeduplicateNutrient($this->imported))->handle(app(LlmClientContract::class));

        $this->assertDatabaseCount('nutrient_mapping_reviews', 0);
    }

    // -------------------------------------------------------------------------
    // Review queue (confidence < 95)
    // -------------------------------------------------------------------------

    public function test_creates_review_when_confidence_is_below_95(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 80, 'reasoning' => 'Probably.']));

        (new DeduplicateNutrient($this->imported))->handle(app(LlmClientContract::class));

        $this->assertDatabaseHas('nutrient_mapping_reviews', [
            'nutrient_id'           => $this->imported->id,
            'suggested_canonical_id' => $this->canonical->id,
            'confidence'            => 80,
            'status'                => 'pending',
        ]);
    }

    public function test_review_stores_reasoning(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 70, 'reasoning' => 'Unclear form.']));

        (new DeduplicateNutrient($this->imported))->handle(app(LlmClientContract::class));

        $review = NutrientMappingReview::first();
        $this->assertSame('Unclear form.', $review->reasoning);
    }

    public function test_does_not_delete_nutrient_when_review_created(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 80, 'reasoning' => 'Maybe.']));

        (new DeduplicateNutrient($this->imported))->handle(app(LlmClientContract::class));

        $this->assertDatabaseHas('nutrients', ['id' => $this->imported->id, 'deleted_at' => null]);
    }

    // -------------------------------------------------------------------------
    // No match
    // -------------------------------------------------------------------------

    public function test_no_action_when_llm_returns_no_match(): void
    {
        $this->mockLlm(json_encode(['match' => null, 'confidence' => 100, 'reasoning' => 'Novel nutrient.']));

        (new DeduplicateNutrient($this->imported))->handle(app(LlmClientContract::class));

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

        (new DeduplicateNutrient($this->imported))->handle(app(LlmClientContract::class));

        $this->assertDatabaseCount('nutrient_mapping_reviews', 0);
    }

    public function test_does_not_create_duplicate_review_on_rerun(): void
    {
        $this->mockLlm(json_encode(['match' => 'Vitamin D', 'confidence' => 80, 'reasoning' => 'Probably.']));

        (new DeduplicateNutrient($this->imported))->handle(app(LlmClientContract::class));

        // Second run: review already exists, should not create another
        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldNotReceive('chat');
        $this->app->instance(LlmClientContract::class, $llm);

        (new DeduplicateNutrient($this->imported))->handle(app(LlmClientContract::class));

        $this->assertDatabaseCount('nutrient_mapping_reviews', 1);
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
