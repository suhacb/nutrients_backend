<?php

namespace Tests\Feature\Console;

use App\Jobs\GenerateIngredientDescription;
use App\Models\Ingredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateIngredientDescriptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function ingredient(array $attrs = []): Ingredient
    {
        return Ingredient::withoutEvents(fn () => Ingredient::factory()->create($attrs));
    }

    // -------------------------------------------------------------------------
    // Default behaviour (no flags)
    // -------------------------------------------------------------------------

    public function test_dispatches_jobs_only_for_ingredients_without_description(): void
    {
        $this->ingredient(['description' => null]);
        $this->ingredient(['description' => null]);
        $this->ingredient(['description' => 'Already has one']);

        $this->artisan('ingredients:generate-descriptions')->assertSuccessful();

        Queue::assertPushed(GenerateIngredientDescription::class, 2);
    }

    public function test_dispatches_jobs_to_ingredients_queue(): void
    {
        $this->ingredient(['description' => null]);

        $this->artisan('ingredients:generate-descriptions')->assertSuccessful();

        Queue::assertPushedOn('ingredients', GenerateIngredientDescription::class);
    }

    public function test_outputs_dispatch_count(): void
    {
        $this->ingredient(['description' => null]);
        $this->ingredient(['description' => null]);

        $this->artisan('ingredients:generate-descriptions')
            ->expectsOutputToContain('2')
            ->assertSuccessful();
    }

    public function test_does_nothing_when_all_ingredients_have_descriptions(): void
    {
        $this->ingredient(['description' => 'Has one']);

        $this->artisan('ingredients:generate-descriptions')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    // -------------------------------------------------------------------------
    // --overwrite flag
    // -------------------------------------------------------------------------

    public function test_overwrite_flag_dispatches_jobs_for_all_ingredients(): void
    {
        $this->ingredient(['description' => null]);
        $this->ingredient(['description' => 'Already has one']);

        $this->artisan('ingredients:generate-descriptions --overwrite')->assertSuccessful();

        Queue::assertPushed(GenerateIngredientDescription::class, 2);
    }

    // -------------------------------------------------------------------------
    // --id flag
    // -------------------------------------------------------------------------

    public function test_id_flag_processes_only_specified_ingredients(): void
    {
        $a = $this->ingredient(['description' => null]);
        $b = $this->ingredient(['description' => null]);
        $this->ingredient(['description' => null]);

        $this->artisan("ingredients:generate-descriptions --id={$a->id} --id={$b->id}")->assertSuccessful();

        Queue::assertPushed(GenerateIngredientDescription::class, 2);
    }

    public function test_id_flag_with_overwrite_regenerates_specified_ingredients(): void
    {
        $a = $this->ingredient(['description' => 'Existing']);
        $this->ingredient(['description' => null]);

        $this->artisan("ingredients:generate-descriptions --id={$a->id} --overwrite")->assertSuccessful();

        Queue::assertPushed(GenerateIngredientDescription::class, 1);
    }

    // -------------------------------------------------------------------------
    // Soft deletes
    // -------------------------------------------------------------------------

    public function test_skips_soft_deleted_ingredients(): void
    {
        Ingredient::withoutEvents(function () {
            Ingredient::factory()->create(['description' => null])->delete();
        });

        $this->artisan('ingredients:generate-descriptions')->assertSuccessful();

        Queue::assertNothingPushed();
    }
}
