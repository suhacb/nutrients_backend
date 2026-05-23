<?php

namespace Tests\Feature\Console;

use App\Models\Nutrient;
use App\Models\NutrientMappingReview;
use App\Models\NutrientSourcePivot;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReviewNutrientMappingsTest extends TestCase
{
    use RefreshDatabase;

    private Source $source;
    private Nutrient $canonical;
    private Nutrient $imported;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

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

    private function pendingReview(array $attrs = []): NutrientMappingReview
    {
        return NutrientMappingReview::create(array_merge([
            'nutrient_id'            => $this->imported->id,
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
            ->expectsQuestion('Action? [m]erge / [k]eep / [s]kip', 's')
            ->assertSuccessful();
    }

    public function test_shows_confidence_score(): void
    {
        $this->pendingReview(['confidence' => 82]);

        $this->artisan('nutrients:review-mappings')
            ->expectsOutputToContain('82')
            ->expectsQuestion('Action? [m]erge / [k]eep / [s]kip', 's')
            ->assertSuccessful();
    }

    public function test_shows_reasoning(): void
    {
        $this->pendingReview(['reasoning' => 'Both refer to the same compound.']);

        $this->artisan('nutrients:review-mappings')
            ->expectsOutputToContain('Both refer to the same compound.')
            ->expectsQuestion('Action? [m]erge / [k]eep / [s]kip', 's')
            ->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // Approve merge
    // -------------------------------------------------------------------------

    public function test_merge_repivots_source_mapping_to_canonical(): void
    {
        $this->pendingReview();

        $this->artisan('nutrients:review-mappings')
            ->expectsQuestion('Action? [m]erge / [k]eep / [s]kip', 'm')
            ->assertSuccessful();

        $this->assertDatabaseHas('nutrient_source_mappings', [
            'nutrient_id' => $this->canonical->id,
            'external_id' => '1114',
        ]);
    }

    public function test_merge_deletes_imported_nutrient(): void
    {
        $this->pendingReview();

        $this->artisan('nutrients:review-mappings')
            ->expectsQuestion('Action? [m]erge / [k]eep / [s]kip', 'm')
            ->assertSuccessful();

        $this->assertDatabaseMissing('nutrients', ['id' => $this->imported->id, 'deleted_at' => null]);
    }

    public function test_merge_marks_review_as_approved(): void
    {
        $review = $this->pendingReview();

        $this->artisan('nutrients:review-mappings')
            ->expectsQuestion('Action? [m]erge / [k]eep / [s]kip', 'm')
            ->assertSuccessful();

        $this->assertSame('approved', $review->fresh()->status);
        $this->assertNotNull($review->fresh()->resolved_at);
    }

    // -------------------------------------------------------------------------
    // Keep (distinct nutrient)
    // -------------------------------------------------------------------------

    public function test_keep_does_not_delete_imported_nutrient(): void
    {
        $this->pendingReview();

        $this->artisan('nutrients:review-mappings')
            ->expectsQuestion('Action? [m]erge / [k]eep / [s]kip', 'k')
            ->assertSuccessful();

        $this->assertDatabaseHas('nutrients', ['id' => $this->imported->id, 'deleted_at' => null]);
    }

    public function test_keep_marks_review_approved_with_keep_decision(): void
    {
        $review = $this->pendingReview();

        $this->artisan('nutrients:review-mappings')
            ->expectsQuestion('Action? [m]erge / [k]eep / [s]kip', 'k')
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
            ->expectsQuestion('Action? [m]erge / [k]eep / [s]kip', 's')
            ->assertSuccessful();

        $this->assertSame('pending', $review->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Multiple reviews
    // -------------------------------------------------------------------------

    public function test_processes_all_pending_reviews(): void
    {
        $extra = Nutrient::withoutEvents(fn () => Nutrient::factory()->create(['name' => 'Boron']));
        NutrientSourcePivot::create([
            'nutrient_id' => $extra->id,
            'source_id'   => $this->source->id,
            'external_id' => '2049',
        ]);

        $canonical2 = Nutrient::withoutEvents(fn () => Nutrient::factory()->create(['name' => 'Trace Minerals']));

        $this->pendingReview();
        NutrientMappingReview::create([
            'nutrient_id'            => $extra->id,
            'suggested_canonical_id' => $canonical2->id,
            'confidence'             => 75,
            'decision_type'          => 'merge',
            'reasoning'              => 'Trace mineral.',
            'status'                 => 'pending',
        ]);

        $this->artisan('nutrients:review-mappings')
            ->expectsQuestion('Action? [m]erge / [k]eep / [s]kip', 's')
            ->expectsQuestion('Action? [m]erge / [k]eep / [s]kip', 's')
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
