<?php

namespace Tests\Feature\Console;

use App\AI\Contracts\LlmClientContract;
use App\Models\LabelNutrientMapping;
use App\Models\Nutrient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class MapLabelNutrientsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function canonical(string $name): Nutrient
    {
        return Nutrient::withoutEvents(
            fn () => Nutrient::factory()->create(['name' => $name, 'is_canonical' => true])
        );
    }

    private function mockLlm(string $json): void
    {
        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')->andReturn($json);
        $this->app->instance(LlmClientContract::class, $llm);
    }

    private function llmNeverCalled(): void
    {
        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldNotReceive('chat');
        $this->app->instance(LlmClientContract::class, $llm);
    }

    // -------------------------------------------------------------------------
    // Auto-approve (confidence >= 95)
    // -------------------------------------------------------------------------

    public function test_creates_approved_mapping_when_confidence_is_95_or_above(): void
    {
        $nutrient = $this->canonical('Protein');

        $this->mockLlm(json_encode(['match' => 'Protein', 'confidence' => 97, 'reasoning' => 'Direct match.']));

        $this->artisan('app:map-label-nutrients --key=protein')->assertSuccessful();

        $this->assertDatabaseHas('label_nutrient_mappings', [
            'label_key'   => 'protein',
            'nutrient_id' => $nutrient->id,
            'status'      => 'approved',
            'confidence'  => 97,
        ]);
    }

    public function test_creates_approved_mapping_when_confidence_is_exactly_95(): void
    {
        $nutrient = $this->canonical('Total Fat');

        $this->mockLlm(json_encode(['match' => 'Total Fat', 'confidence' => 95, 'reasoning' => 'Same.']));

        $this->artisan('app:map-label-nutrients --key=fat')->assertSuccessful();

        $this->assertDatabaseHas('label_nutrient_mappings', [
            'label_key' => 'fat',
            'status'    => 'approved',
        ]);
    }

    // -------------------------------------------------------------------------
    // Pending (confidence < 95)
    // -------------------------------------------------------------------------

    public function test_creates_pending_mapping_when_confidence_is_below_95(): void
    {
        $nutrient = $this->canonical('Protein');

        $this->mockLlm(json_encode(['match' => 'Protein', 'confidence' => 80, 'reasoning' => 'Probably.']));

        $this->artisan('app:map-label-nutrients --key=protein')->assertSuccessful();

        $this->assertDatabaseHas('label_nutrient_mappings', [
            'label_key'   => 'protein',
            'nutrient_id' => $nutrient->id,
            'status'      => 'pending',
            'confidence'  => 80,
        ]);
    }

    public function test_stores_reasoning_on_mapping(): void
    {
        $this->canonical('Protein');

        $this->mockLlm(json_encode(['match' => 'Protein', 'confidence' => 97, 'reasoning' => 'Exact name match.']));

        $this->artisan('app:map-label-nutrients --key=protein')->assertSuccessful();

        $this->assertSame('Exact name match.', LabelNutrientMapping::first()->reasoning);
    }

    // -------------------------------------------------------------------------
    // No match
    // -------------------------------------------------------------------------

    public function test_skips_key_when_llm_returns_no_match(): void
    {
        $this->canonical('Protein');

        $this->mockLlm(json_encode(['match' => null, 'confidence' => 0, 'reasoning' => 'Unknown.']));

        $this->artisan('app:map-label-nutrients --key=protein')->assertSuccessful();

        $this->assertDatabaseCount('label_nutrient_mappings', 0);
    }

    public function test_skips_key_when_matched_canonical_does_not_exist(): void
    {
        $this->canonical('Protein');

        $this->mockLlm(json_encode(['match' => 'NonExistentNutrient', 'confidence' => 97, 'reasoning' => 'Oops.']));

        $this->artisan('app:map-label-nutrients --key=protein')->assertSuccessful();

        $this->assertDatabaseCount('label_nutrient_mappings', 0);
    }

    // -------------------------------------------------------------------------
    // Idempotency
    // -------------------------------------------------------------------------

    public function test_skips_key_that_already_has_a_mapping(): void
    {
        $nutrient = $this->canonical('Protein');
        LabelNutrientMapping::create([
            'label_key'   => 'protein',
            'nutrient_id' => $nutrient->id,
            'confidence'  => 97,
            'reasoning'   => 'Already mapped.',
            'status'      => 'approved',
        ]);

        $this->llmNeverCalled();

        $this->artisan('app:map-label-nutrients --key=protein')->assertSuccessful();

        $this->assertDatabaseCount('label_nutrient_mappings', 1);
    }

    public function test_skips_pending_key_on_rerun(): void
    {
        $nutrient = $this->canonical('Protein');
        LabelNutrientMapping::create([
            'label_key'   => 'protein',
            'nutrient_id' => $nutrient->id,
            'confidence'  => 70,
            'reasoning'   => 'Pending review.',
            'status'      => 'pending',
        ]);

        $this->llmNeverCalled();

        $this->artisan('app:map-label-nutrients --key=protein')->assertSuccessful();

        $this->assertDatabaseCount('label_nutrient_mappings', 1);
    }

    // -------------------------------------------------------------------------
    // --key flag
    // -------------------------------------------------------------------------

    public function test_key_flag_processes_only_specified_keys(): void
    {
        $proteinNutrient = $this->canonical('Protein');
        $this->canonical('Total Fat');

        $this->mockLlm(json_encode(['match' => 'Protein', 'confidence' => 97, 'reasoning' => 'Direct.']));

        $this->artisan('app:map-label-nutrients --key=protein')->assertSuccessful();

        $this->assertDatabaseHas('label_nutrient_mappings', ['label_key' => 'protein']);
        $this->assertDatabaseMissing('label_nutrient_mappings', ['label_key' => 'fat']);
    }

    public function test_unknown_key_is_skipped_gracefully(): void
    {
        $this->llmNeverCalled();

        $this->artisan('app:map-label-nutrients --key=unknownLabelKey')->assertSuccessful();

        $this->assertDatabaseCount('label_nutrient_mappings', 0);
    }

    // -------------------------------------------------------------------------
    // --dry-run
    // -------------------------------------------------------------------------

    public function test_dry_run_does_not_persist_mappings(): void
    {
        $this->canonical('Protein');

        $this->mockLlm(json_encode(['match' => 'Protein', 'confidence' => 97, 'reasoning' => 'Match.']));

        $this->artisan('app:map-label-nutrients --key=protein --dry-run')->assertSuccessful();

        $this->assertDatabaseCount('label_nutrient_mappings', 0);
    }

    public function test_dry_run_outputs_key_and_matched_nutrient(): void
    {
        $this->canonical('Protein');

        $this->mockLlm(json_encode(['match' => 'Protein', 'confidence' => 97, 'reasoning' => 'Match.']));

        $this->artisan('app:map-label-nutrients --key=protein --dry-run')
            ->expectsOutputToContain('protein')
            ->expectsOutputToContain('Protein')
            ->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // No canonical nutrients
    // -------------------------------------------------------------------------

    public function test_does_nothing_when_no_canonical_nutrients_exist(): void
    {
        $this->llmNeverCalled();

        $this->artisan('app:map-label-nutrients --key=protein')->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // Invalid LLM response
    // -------------------------------------------------------------------------

    public function test_handles_invalid_json_response_gracefully(): void
    {
        $this->canonical('Protein');

        $this->mockLlm('not-valid-json');

        $this->artisan('app:map-label-nutrients --key=protein')->assertSuccessful();

        $this->assertDatabaseCount('label_nutrient_mappings', 0);
    }

    // -------------------------------------------------------------------------
    // LLM options
    // -------------------------------------------------------------------------

    public function test_uses_smart_model_for_classification(): void
    {
        $this->canonical('Protein');

        $llm = Mockery::mock(LlmClientContract::class);
        $llm->shouldReceive('chat')
            ->once()
            ->withArgs(fn (array $messages, array $opts) => ($opts['model'] ?? '') === config('ai.ollama.models.smart'))
            ->andReturn(json_encode(['match' => 'Protein', 'confidence' => 97, 'reasoning' => 'Match.']));
        $this->app->instance(LlmClientContract::class, $llm);
        $this->addToAssertionCount(1);

        $this->artisan('app:map-label-nutrients --key=protein')->assertSuccessful();
    }
}
