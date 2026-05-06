<?php

namespace Tests\Feature\Console;

use App\Jobs\GenerateNutrientDescription;
use App\Models\Nutrient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateNutrientDescriptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function nutrient(array $attrs = []): Nutrient
    {
        return Nutrient::withoutEvents(fn () => Nutrient::factory()->create($attrs));
    }

    // -------------------------------------------------------------------------
    // Default behaviour (no flags)
    // -------------------------------------------------------------------------

    public function test_dispatches_jobs_only_for_nutrients_without_description(): void
    {
        $this->nutrient(['description' => null]);
        $this->nutrient(['description' => null]);
        $this->nutrient(['description' => 'Already has one']);

        $this->artisan('nutrients:generate-descriptions')->assertSuccessful();

        Queue::assertPushed(GenerateNutrientDescription::class, 2);
    }

    public function test_dispatches_jobs_to_nutrients_queue(): void
    {
        $this->nutrient(['description' => null]);

        $this->artisan('nutrients:generate-descriptions')->assertSuccessful();

        Queue::assertPushedOn('nutrients', GenerateNutrientDescription::class);
    }

    public function test_outputs_dispatch_count(): void
    {
        $this->nutrient(['description' => null]);
        $this->nutrient(['description' => null]);

        $this->artisan('nutrients:generate-descriptions')
            ->expectsOutputToContain('2')
            ->assertSuccessful();
    }

    public function test_does_nothing_when_all_nutrients_have_descriptions(): void
    {
        $this->nutrient(['description' => 'Has one']);

        $this->artisan('nutrients:generate-descriptions')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    // -------------------------------------------------------------------------
    // --overwrite flag
    // -------------------------------------------------------------------------

    public function test_overwrite_flag_dispatches_jobs_for_all_nutrients(): void
    {
        $this->nutrient(['description' => null]);
        $this->nutrient(['description' => 'Already has one']);

        $this->artisan('nutrients:generate-descriptions --overwrite')->assertSuccessful();

        Queue::assertPushed(GenerateNutrientDescription::class, 2);
    }

    // -------------------------------------------------------------------------
    // --id flag
    // -------------------------------------------------------------------------

    public function test_id_flag_processes_only_specified_nutrients(): void
    {
        $a = $this->nutrient(['description' => null]);
        $b = $this->nutrient(['description' => null]);
        $this->nutrient(['description' => null]);

        $this->artisan("nutrients:generate-descriptions --id={$a->id} --id={$b->id}")->assertSuccessful();

        Queue::assertPushed(GenerateNutrientDescription::class, 2);
    }

    public function test_id_flag_with_overwrite_regenerates_specified_nutrients(): void
    {
        $a = $this->nutrient(['description' => 'Existing']);
        $this->nutrient(['description' => null]);

        $this->artisan("nutrients:generate-descriptions --id={$a->id} --overwrite")->assertSuccessful();

        Queue::assertPushed(GenerateNutrientDescription::class, 1);
    }

    // -------------------------------------------------------------------------
    // Soft deletes
    // -------------------------------------------------------------------------

    public function test_skips_soft_deleted_nutrients(): void
    {
        Nutrient::withoutEvents(function () {
            Nutrient::factory()->create(['description' => null])->delete();
        });

        $this->artisan('nutrients:generate-descriptions')->assertSuccessful();

        Queue::assertNothingPushed();
    }
}
