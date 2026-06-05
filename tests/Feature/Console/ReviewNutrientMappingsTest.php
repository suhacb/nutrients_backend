<?php

namespace Tests\Feature\Console;

use App\Models\Nutrient;
use App\Models\NutrientMappingReview;
use App\Models\Source;
use App\Models\SourceNutrient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReviewNutrientMappingsTest extends TestCase
{
    use RefreshDatabase;

    private Source $source;
    private Nutrient $canonical;
    private SourceNutrient $sourceNutrient;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->source = Source::factory()->create(['slug' => 'usda', 'name' => 'USDA']);

        $this->canonical = Nutrient::withoutEvents(
            fn () => Nutrient::factory()->create(['name' => 'Vitamin D', 'is_canonical' => true])
        );

        $this->sourceNutrient = SourceNutrient::create([
            'source_id'   => $this->source->id,
            'external_id' => '1114',
            'name'        => 'Vitamin D (D2+D3)',
        ]);
    }

    private function pendingReview(array $attrs = []): NutrientMappingReview
    {
        return NutrientMappingReview::create(array_merge([
            'source_nutrient_id'     => $this->sourceNutrient->id,
            'suggested_canonical_id' => $this->canonical->id,
            'confidence'             => 80,
            'decision_type'          => 'merge',
            'reasoning'              => 'Probably the same substance.',
            'status'                 => 'pending',
        ], $attrs));
    }

    // -------------------------------------------------------------------------
    // No pending reviews
    // -------------------------------------------------------------------------

    public function test_reports_no_pending_reviews_and_exits(): void
    {
        $this->artisan('nutrients:review-mappings')
            ->expectsOutputToContain('No pending')
            ->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // Listing
    // -------------------------------------------------------------------------

    public function test_shows_nutrient_name_and_canonical_suggestion(): void
    {
        $this->pendingReview();

        $this->artisan('nutrients:review-mappings')
            ->expectsOutputToContain('Vitamin D (D2+D3)')
            ->expectsOutputToContain('Vitamin D')
            ->expectsQuestion('Action? [m]erge / [p]arent / [k]eep / [s]kip', 's')
            ->assertSuccessful();
    }

    public function test_shows_confidence_score(): void
    {
        $this->pendingReview(['confidence' => 82]);

        $this->artisan('nutrients:review-mappings')
            ->expectsOutputToContain('82')
            ->expectsQuestion('Action? [m]erge / [p]arent / [k]eep / [s]kip', 's')
            ->assertSuccessful();
    }

    public function test_shows_reasoning(): void
    {
        $this->pendingReview(['reasoning' => 'Both refer to the same compound.']);

        $this->artisan('nutrients:review-mappings')
            ->expectsOutputToContain('Both refer to the same compound.')
            ->expectsQuestion('Action? [m]erge / [p]arent / [k]eep / [s]kip', 's')
            ->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // Approve merge
    // -------------------------------------------------------------------------

    public function test_merge_resolves_source_nutrient_to_canonical(): void
    {
        $this->pendingReview();

        $this->artisan('nutrients:review-mappings')
            ->expectsQuestion('Action? [m]erge / [p]arent / [k]eep / [s]kip', 'm')
            ->assertSuccessful();

        $this->assertDatabaseHas('source_nutrients', [
            'id'          => $this->sourceNutrient->id,
            'nutrient_id' => $this->canonical->id,
        ]);
        $this->assertNotNull($this->sourceNutrient->fresh()->resolved_at);
    }

    public function test_merge_marks_review_as_approved(): void
    {
        $review = $this->pendingReview();

        $this->artisan('nutrients:review-mappings')
            ->expectsQuestion('Action? [m]erge / [p]arent / [k]eep / [s]kip', 'm')
            ->assertSuccessful();

        $this->assertSame('approved', $review->fresh()->status);
        $this->assertNotNull($review->fresh()->resolved_at);
    }

    // -------------------------------------------------------------------------
    // Approve parent
    // -------------------------------------------------------------------------

    public function test_parent_creates_new_canonical_as_child(): void
    {
        $this->pendingReview(['decision_type' => 'parent']);

        $this->artisan('nutrients:review-mappings')
            ->expectsQuestion('Action? [m]erge / [p]arent / [k]eep / [s]kip', 'p')
            ->assertSuccessful();

        $this->assertDatabaseHas('nutrients', [
            'name'      => 'Vitamin D (D2+D3)',
            'parent_id' => $this->canonical->id,
        ]);
    }

    public function test_parent_resolves_source_nutrient(): void
    {
        $this->pendingReview(['decision_type' => 'parent']);

        $this->artisan('nutrients:review-mappings')
            ->expectsQuestion('Action? [m]erge / [p]arent / [k]eep / [s]kip', 'p')
            ->assertSuccessful();

        $this->assertNotNull($this->sourceNutrient->fresh()->nutrient_id);
        $this->assertNotNull($this->sourceNutrient->fresh()->resolved_at);
    }

    public function test_parent_marks_review_approved_with_parent_decision(): void
    {
        $review = $this->pendingReview(['decision_type' => 'parent']);

        $this->artisan('nutrients:review-mappings')
            ->expectsQuestion('Action? [m]erge / [p]arent / [k]eep / [s]kip', 'p')
            ->assertSuccessful();

        $review->refresh();
        $this->assertSame('approved', $review->status);
        $this->assertSame('parent', $review->decision_type);
        $this->assertNotNull($review->resolved_at);
    }

    // -------------------------------------------------------------------------
    // Keep (distinct nutrient)
    // -------------------------------------------------------------------------

    public function test_keep_promotes_source_nutrient_to_new_canonical(): void
    {
        $this->pendingReview();

        $this->artisan('nutrients:review-mappings')
            ->expectsQuestion('Action? [m]erge / [p]arent / [k]eep / [s]kip', 'k')
            ->assertSuccessful();

        $this->assertDatabaseHas('nutrients', ['name' => 'Vitamin D (D2+D3)', 'is_canonical' => true]);
        $this->assertNotNull($this->sourceNutrient->fresh()->nutrient_id);
    }

    public function test_keep_marks_review_approved_with_keep_decision(): void
    {
        $review = $this->pendingReview();

        $this->artisan('nutrients:review-mappings')
            ->expectsQuestion('Action? [m]erge / [p]arent / [k]eep / [s]kip', 'k')
            ->assertSuccessful();

        $review->refresh();
        $this->assertSame('approved', $review->status);
        $this->assertSame('keep', $review->decision_type);
        $this->assertNotNull($review->resolved_at);
    }

    // -------------------------------------------------------------------------
    // Skip
    // -------------------------------------------------------------------------

    public function test_skip_leaves_review_pending(): void
    {
        $review = $this->pendingReview();

        $this->artisan('nutrients:review-mappings')
            ->expectsQuestion('Action? [m]erge / [p]arent / [k]eep / [s]kip', 's')
            ->assertSuccessful();

        $this->assertSame('pending', $review->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Multiple reviews
    // -------------------------------------------------------------------------

    public function test_processes_all_pending_reviews(): void
    {
        $sn2 = SourceNutrient::create([
            'source_id'   => $this->source->id,
            'external_id' => '2049',
            'name'        => 'Boron',
        ]);
        $canonical2 = Nutrient::withoutEvents(fn () => Nutrient::factory()->create(['name' => 'Trace Minerals', 'is_canonical' => true]));

        $this->pendingReview();
        NutrientMappingReview::create([
            'source_nutrient_id'     => $sn2->id,
            'suggested_canonical_id' => $canonical2->id,
            'confidence'             => 75,
            'decision_type'          => 'merge',
            'reasoning'              => 'Trace mineral.',
            'status'                 => 'pending',
        ]);

        $this->artisan('nutrients:review-mappings')
            ->expectsQuestion('Action? [m]erge / [p]arent / [k]eep / [s]kip', 's')
            ->expectsQuestion('Action? [m]erge / [p]arent / [k]eep / [s]kip', 's')
            ->assertSuccessful();
    }

    public function test_skips_already_resolved_reviews(): void
    {
        $this->pendingReview(['status' => 'approved', 'resolved_at' => now()]);

        $this->artisan('nutrients:review-mappings')
            ->expectsOutputToContain('No pending')
            ->assertSuccessful();
    }
}
