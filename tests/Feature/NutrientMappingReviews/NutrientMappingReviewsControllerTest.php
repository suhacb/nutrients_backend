<?php

namespace Tests\Feature\NutrientMappingReviews;

use App\Models\Ingredient;
use App\Models\Nutrient;
use App\Models\NutrientMappingReview;
use App\Models\Source;
use App\Models\SourceNutrient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\LoginTestUser;
use Tests\MakesUnit;
use Tests\TestCase;

class NutrientMappingReviewsControllerTest extends TestCase
{
    use RefreshDatabase, LoginTestUser, MakesUnit;

    protected Source $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->login();
        Queue::fake();
        $this->source = Source::factory()->create(['slug' => 'test', 'name' => 'Test']);
    }

    private function makeSourceNutrient(array $attrs = []): SourceNutrient
    {
        return SourceNutrient::create(array_merge([
            'source_id'   => $this->source->id,
            'external_id' => (string) rand(1000, 9999),
            'name'        => 'Test Nutrient',
        ], $attrs));
    }

    private function makeReview(array $overrides = []): NutrientMappingReview
    {
        $sourceNutrient = $this->makeSourceNutrient();
        $canonical      = Nutrient::factory()->create(['is_canonical' => true]);

        return NutrientMappingReview::create(array_merge([
            'source_nutrient_id'     => $sourceNutrient->id,
            'suggested_canonical_id' => $canonical->id,
            'confidence'             => 87,
            'decision_type'          => 'merge',
            'reasoning'              => 'Very similar nutrients.',
            'status'                 => 'pending',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // GET /api/nutrient-mapping-reviews
    // -------------------------------------------------------------------------

    public function test_index_returns_pending_reviews_by_default(): void
    {
        $this->makeReview(['status' => 'pending']);
        $this->makeReview(['status' => 'approved']);

        $this->withHeaders($this->makeAuthRequestHeader())
             ->getJson(route('nutrient-mapping-reviews.index'))
             ->assertStatus(200)
             ->assertJsonCount(1, 'data');
    }

    public function test_index_accepts_status_filter(): void
    {
        $this->makeReview(['status' => 'pending']);
        $this->makeReview(['status' => 'approved']);
        $this->makeReview(['status' => 'rejected']);

        $response = $this->withHeaders($this->makeAuthRequestHeader())
             ->getJson(route('nutrient-mapping-reviews.index') . '?status=approved')
             ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('approved', $response->json('data.0.status'));
    }

    public function test_index_response_includes_source_nutrient_and_suggested_canonical(): void
    {
        $sn        = $this->makeSourceNutrient(['name' => 'Vitamin B12 (imported)']);
        $canonical = Nutrient::factory()->create(['name' => 'Cobalamin', 'is_canonical' => true]);

        NutrientMappingReview::create([
            'source_nutrient_id'     => $sn->id,
            'suggested_canonical_id' => $canonical->id,
            'confidence'             => 88,
            'decision_type'          => 'merge',
            'reasoning'              => 'They are the same.',
            'status'                 => 'pending',
        ]);

        $item = $this->withHeaders($this->makeAuthRequestHeader())
             ->getJson(route('nutrient-mapping-reviews.index'))
             ->assertStatus(200)
             ->json('data.0');

        $this->assertEquals('Vitamin B12 (imported)', $item['source_nutrient']['name']);
        $this->assertEquals('Cobalamin',               $item['suggested_canonical']['name']);
        $this->assertEquals(88,                        $item['confidence']);
        $this->assertEquals('merge',                   $item['decision_type']);
        $this->assertEquals('pending',                 $item['status']);
        $this->assertEquals('They are the same.',      $item['reasoning']);
    }

    // -------------------------------------------------------------------------
    // PATCH /api/nutrient-mapping-reviews/{nutrientMappingReview}
    // -------------------------------------------------------------------------

    public function test_resolve_returns_404_for_nonexistent(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', 9999), ['decision' => 'keep'])
             ->assertStatus(404);
    }

    public function test_resolve_returns_422_for_invalid_decision(): void
    {
        $review = $this->makeReview();

        $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', $review), ['decision' => 'dunno'])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['decision']);
    }

    public function test_resolve_returns_422_when_decision_missing(): void
    {
        $review = $this->makeReview();

        $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', $review), [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['decision']);
    }

    public function test_resolve_returns_409_for_already_resolved_review(): void
    {
        $review = $this->makeReview(['status' => 'approved', 'resolved_at' => now()]);

        $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', $review), ['decision' => 'keep'])
             ->assertStatus(409)
             ->assertJsonFragment(['message' => 'This review has already been resolved.']);
    }

    public function test_resolve_merge_promotes_ingredient_pivots_to_canonical(): void
    {
        $unit      = $this->makeUnit();
        $sn        = $this->makeSourceNutrient();
        $canonical = Nutrient::factory()->create(['is_canonical' => true]);
        $ingredient = Ingredient::factory()->create();

        DB::table('ingredient_source_nutrient')->insert([
            'ingredient_id'    => $ingredient->id,
            'source_nutrient_id' => $sn->id,
            'amount'           => 5.0,
            'amount_unit_id'   => $unit->id,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $review = NutrientMappingReview::create([
            'source_nutrient_id'     => $sn->id,
            'suggested_canonical_id' => $canonical->id,
            'confidence'             => 88,
            'decision_type'          => 'merge',
            'reasoning'              => 'Same thing.',
            'status'                 => 'pending',
        ]);

        $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', $review), ['decision' => 'merge'])
             ->assertStatus(200);

        $this->assertDatabaseHas('ingredient_nutrient', [
            'ingredient_id' => $ingredient->id,
            'nutrient_id'   => $canonical->id,
            'amount'        => 5.0,
        ]);
        $this->assertDatabaseCount('ingredient_source_nutrient', 0);
    }

    public function test_resolve_merge_resolves_source_nutrient(): void
    {
        $sn        = $this->makeSourceNutrient();
        $canonical = Nutrient::factory()->create(['is_canonical' => true]);

        $review = NutrientMappingReview::create([
            'source_nutrient_id'     => $sn->id,
            'suggested_canonical_id' => $canonical->id,
            'confidence'             => 88,
            'decision_type'          => 'merge',
            'reasoning'              => 'Same thing.',
            'status'                 => 'pending',
        ]);

        $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', $review), ['decision' => 'merge'])
             ->assertStatus(200);

        $this->assertDatabaseHas('source_nutrients', [
            'id'          => $sn->id,
            'nutrient_id' => $canonical->id,
        ]);
        $this->assertNotNull($sn->fresh()->resolved_at);
    }

    public function test_resolve_merge_marks_review_approved(): void
    {
        $review = $this->makeReview();

        $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', $review), ['decision' => 'merge'])
             ->assertStatus(200);

        $fresh = $review->fresh();
        $this->assertEquals('approved', $fresh->status);
        $this->assertEquals('merge',    $fresh->decision_type);
        $this->assertNotNull($fresh->resolved_at);
    }

    public function test_resolve_keep_promotes_source_nutrient_to_new_canonical(): void
    {
        $sn     = $this->makeSourceNutrient(['name' => 'Novel Vitamin X']);
        $review = NutrientMappingReview::create([
            'source_nutrient_id'     => $sn->id,
            'suggested_canonical_id' => null,
            'confidence'             => 0,
            'decision_type'          => 'merge',
            'reasoning'              => 'Search failed.',
            'status'                 => 'pending',
        ]);

        $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', $review), ['decision' => 'keep'])
             ->assertStatus(200);

        $this->assertDatabaseHas('nutrients', ['name' => 'Novel Vitamin X', 'is_canonical' => true]);
        $this->assertNotNull($sn->fresh()->nutrient_id);
    }

    public function test_resolve_keep_marks_review_approved_with_keep_decision(): void
    {
        $review = $this->makeReview();

        $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', $review), ['decision' => 'keep'])
             ->assertStatus(200);

        $fresh = $review->fresh();
        $this->assertEquals('approved', $fresh->status);
        $this->assertEquals('keep',     $fresh->decision_type);
        $this->assertNotNull($fresh->resolved_at);
    }

    public function test_resolve_reject_marks_review_rejected(): void
    {
        $review = $this->makeReview();

        $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', $review), ['decision' => 'reject'])
             ->assertStatus(200);

        $fresh = $review->fresh();
        $this->assertEquals('rejected', $fresh->status);
        $this->assertNotNull($fresh->resolved_at);
    }

    // -------------------------------------------------------------------------
    // Parent classification
    // -------------------------------------------------------------------------

    public function test_resolve_parent_creates_new_canonical_as_child(): void
    {
        $sn        = $this->makeSourceNutrient(['name' => 'Phylloquinone']);
        $canonical = Nutrient::factory()->create(['name' => 'Vitamin K', 'is_canonical' => true]);

        $review = NutrientMappingReview::create([
            'source_nutrient_id'     => $sn->id,
            'suggested_canonical_id' => $canonical->id,
            'confidence'             => 80,
            'decision_type'          => 'parent',
            'reasoning'              => 'Specific form.',
            'status'                 => 'pending',
        ]);

        $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', $review), ['decision' => 'parent'])
             ->assertStatus(200);

        $this->assertDatabaseHas('nutrients', [
            'name'      => 'Phylloquinone',
            'parent_id' => $canonical->id,
        ]);
    }

    public function test_resolve_parent_resolves_source_nutrient(): void
    {
        $sn        = $this->makeSourceNutrient(['name' => 'Phylloquinone']);
        $canonical = Nutrient::factory()->create(['name' => 'Vitamin K', 'is_canonical' => true]);

        $review = NutrientMappingReview::create([
            'source_nutrient_id'     => $sn->id,
            'suggested_canonical_id' => $canonical->id,
            'confidence'             => 80,
            'decision_type'          => 'parent',
            'reasoning'              => 'Specific form.',
            'status'                 => 'pending',
        ]);

        $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', $review), ['decision' => 'parent'])
             ->assertStatus(200);

        $this->assertNotNull($sn->fresh()->nutrient_id);
        $this->assertNotNull($sn->fresh()->resolved_at);
    }

    public function test_resolve_parent_marks_review_approved(): void
    {
        $sn        = $this->makeSourceNutrient();
        $canonical = Nutrient::factory()->create(['is_canonical' => true]);

        $review = NutrientMappingReview::create([
            'source_nutrient_id'     => $sn->id,
            'suggested_canonical_id' => $canonical->id,
            'confidence'             => 80,
            'decision_type'          => 'parent',
            'reasoning'              => 'Specific form.',
            'status'                 => 'pending',
        ]);

        $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', $review), ['decision' => 'parent'])
             ->assertStatus(200);

        $fresh = $review->fresh();
        $this->assertEquals('approved', $fresh->status);
        $this->assertEquals('parent',   $fresh->decision_type);
        $this->assertNotNull($fresh->resolved_at);
    }

    // -------------------------------------------------------------------------
    // canonical_id override
    // -------------------------------------------------------------------------

    public function test_resolve_merge_with_canonical_id_override_uses_specified_canonical(): void
    {
        $unit      = $this->makeUnit();
        $sn        = $this->makeSourceNutrient();
        $suggested = Nutrient::factory()->create(['is_canonical' => true]);
        $override  = Nutrient::factory()->create(['is_canonical' => true]);
        $ingredient = Ingredient::factory()->create();

        DB::table('ingredient_source_nutrient')->insert([
            'ingredient_id'    => $ingredient->id,
            'source_nutrient_id' => $sn->id,
            'amount'           => 3.0,
            'amount_unit_id'   => $unit->id,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $review = NutrientMappingReview::create([
            'source_nutrient_id'     => $sn->id,
            'suggested_canonical_id' => $suggested->id,
            'confidence'             => 80,
            'decision_type'          => 'merge',
            'reasoning'              => 'Similar.',
            'status'                 => 'pending',
        ]);

        $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', $review), [
                 'decision'     => 'merge',
                 'canonical_id' => $override->id,
             ])
             ->assertStatus(200);

        $this->assertDatabaseHas('ingredient_nutrient', [
            'ingredient_id' => $ingredient->id,
            'nutrient_id'   => $override->id,
            'amount'        => 3.0,
        ]);
        $this->assertDatabaseHas('source_nutrients', [
            'id'          => $sn->id,
            'nutrient_id' => $override->id,
        ]);
    }

    public function test_resolve_returns_422_for_merge_when_no_suggestion_and_no_canonical_id(): void
    {
        $sn     = $this->makeSourceNutrient();
        $review = NutrientMappingReview::create([
            'source_nutrient_id'     => $sn->id,
            'suggested_canonical_id' => null,
            'confidence'             => 0,
            'decision_type'          => 'merge',
            'reasoning'              => 'Search failed.',
            'status'                 => 'pending',
        ]);

        $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', $review), ['decision' => 'merge'])
             ->assertStatus(422)
             ->assertJsonFragment(['message' => 'This review has no suggested canonical. Provide canonical_id.']);
    }

    public function test_resolve_returns_review_resource(): void
    {
        $review = $this->makeReview();

        $json = $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', $review), ['decision' => 'keep'])
             ->assertStatus(200)
             ->json();

        $this->assertArrayHasKey('id',            $json);
        $this->assertArrayHasKey('status',        $json);
        $this->assertArrayHasKey('confidence',    $json);
        $this->assertArrayHasKey('decision_type', $json);
        $this->assertArrayHasKey('reasoning',     $json);
    }

    protected function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }
}
