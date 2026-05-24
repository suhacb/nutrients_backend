<?php

namespace Tests\Feature\NutrientMappingReviews;

use App\Models\Ingredient;
use App\Models\Nutrient;
use App\Models\NutrientMappingReview;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\LoginTestUser;
use Tests\MakesUnit;
use Tests\TestCase;

class NutrientMappingReviewsControllerTest extends TestCase
{
    use RefreshDatabase, LoginTestUser, MakesUnit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->login();
        Queue::fake();
    }

    private function makeReview(array $overrides = []): NutrientMappingReview
    {
        $imported  = Nutrient::factory()->create();
        $canonical = Nutrient::factory()->create();

        return NutrientMappingReview::create(array_merge([
            'nutrient_id'            => $imported->id,
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

    public function test_index_response_includes_nutrient_and_suggested_canonical(): void
    {
        $imported  = Nutrient::factory()->create(['name' => 'VitB12']);
        $canonical = Nutrient::factory()->create(['name' => 'Cobalamin']);

        NutrientMappingReview::create([
            'nutrient_id'            => $imported->id,
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

        $this->assertEquals('VitB12',            $item['nutrient']['name']);
        $this->assertEquals('Cobalamin',          $item['suggested_canonical']['name']);
        $this->assertEquals(88,                   $item['confidence']);
        $this->assertEquals('merge',              $item['decision_type']);
        $this->assertEquals('pending',            $item['status']);
        $this->assertEquals('They are the same.', $item['reasoning']);
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

    public function test_resolve_merge_repivots_ingredient_nutrient(): void
    {
        $unit       = $this->makeUnit();
        $imported   = Nutrient::factory()->create();
        $canonical  = Nutrient::factory()->create();
        $ingredient = Ingredient::factory()->create();
        $ingredient->nutrients()->attach($imported->id, ['amount' => 5.0, 'amount_unit_id' => $unit->id]);

        $review = NutrientMappingReview::create([
            'nutrient_id'            => $imported->id,
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
        ]);
        $this->assertDatabaseMissing('ingredient_nutrient', [
            'ingredient_id' => $ingredient->id,
            'nutrient_id'   => $imported->id,
        ]);
    }

    public function test_resolve_merge_repivots_source_mapping(): void
    {
        $source    = Source::factory()->create();
        $imported  = Nutrient::factory()->create();
        $canonical = Nutrient::factory()->create();

        DB::table('nutrient_source_mappings')->insert([
            'nutrient_id' => $imported->id,
            'source_id'   => $source->id,
            'external_id' => 'X001',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $review = NutrientMappingReview::create([
            'nutrient_id'            => $imported->id,
            'suggested_canonical_id' => $canonical->id,
            'confidence'             => 88,
            'decision_type'          => 'merge',
            'reasoning'              => 'Same thing.',
            'status'                 => 'pending',
        ]);

        $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', $review), ['decision' => 'merge'])
             ->assertStatus(200);

        $this->assertDatabaseHas('nutrient_source_mappings', [
            'nutrient_id' => $canonical->id,
            'external_id' => 'X001',
        ]);
    }

    public function test_resolve_merge_deletes_imported_nutrient(): void
    {
        $imported  = Nutrient::factory()->create();
        $canonical = Nutrient::factory()->create();

        $review = NutrientMappingReview::create([
            'nutrient_id'            => $imported->id,
            'suggested_canonical_id' => $canonical->id,
            'confidence'             => 88,
            'decision_type'          => 'merge',
            'reasoning'              => 'Same.',
            'status'                 => 'pending',
        ]);

        $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', $review), ['decision' => 'merge'])
             ->assertStatus(200);

        $this->assertDatabaseMissing('nutrients', ['id' => $imported->id]);
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

    public function test_resolve_keep_does_not_delete_nutrient(): void
    {
        $review     = $this->makeReview();
        $nutrientId = $review->nutrient_id;

        $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', $review), ['decision' => 'keep'])
             ->assertStatus(200);

        $this->assertDatabaseHas('nutrients', ['id' => $nutrientId]);
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

    public function test_resolve_parent_sets_parent_id_on_nutrient(): void
    {
        $imported  = Nutrient::factory()->create();
        $canonical = Nutrient::factory()->create(['is_canonical' => true]);

        $review = NutrientMappingReview::create([
            'nutrient_id'            => $imported->id,
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
            'id'        => $imported->id,
            'parent_id' => $canonical->id,
        ]);
    }

    public function test_resolve_parent_does_not_delete_nutrient(): void
    {
        $imported  = Nutrient::factory()->create();
        $canonical = Nutrient::factory()->create(['is_canonical' => true]);

        $review = NutrientMappingReview::create([
            'nutrient_id'            => $imported->id,
            'suggested_canonical_id' => $canonical->id,
            'confidence'             => 80,
            'decision_type'          => 'parent',
            'reasoning'              => 'Specific form.',
            'status'                 => 'pending',
        ]);

        $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', $review), ['decision' => 'parent'])
             ->assertStatus(200);

        $this->assertDatabaseHas('nutrients', ['id' => $imported->id, 'deleted_at' => null]);
    }

    public function test_resolve_parent_marks_review_approved(): void
    {
        $imported  = Nutrient::factory()->create();
        $canonical = Nutrient::factory()->create(['is_canonical' => true]);

        $review = NutrientMappingReview::create([
            'nutrient_id'            => $imported->id,
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
        $unit       = $this->makeUnit();
        $imported   = Nutrient::factory()->create();
        $suggested  = Nutrient::factory()->create(['is_canonical' => true]);
        $override   = Nutrient::factory()->create(['is_canonical' => true]);
        $ingredient = Ingredient::factory()->create();
        $ingredient->nutrients()->attach($imported->id, ['amount' => 3.0, 'amount_unit_id' => $unit->id]);

        $review = NutrientMappingReview::create([
            'nutrient_id'            => $imported->id,
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
        ]);
        $this->assertDatabaseMissing('nutrients', ['id' => $imported->id]);
    }

    public function test_resolve_returns_422_when_canonical_id_is_not_a_canonical_nutrient(): void
    {
        $review     = $this->makeReview();
        $nonCanonical = Nutrient::factory()->create(['is_canonical' => false]);

        $this->withHeaders($this->makeAuthRequestHeader())
             ->patchJson(route('nutrient-mapping-reviews.resolve', $review), [
                 'decision'     => 'merge',
                 'canonical_id' => $nonCanonical->id,
             ])
             ->assertStatus(422)
             ->assertJsonFragment(['message' => 'The specified nutrient is not a canonical nutrient.']);
    }

    public function test_resolve_returns_422_for_merge_when_no_suggestion_and_no_canonical_id(): void
    {
        $imported = Nutrient::factory()->create();

        $review = NutrientMappingReview::create([
            'nutrient_id'            => $imported->id,
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
