<?php

namespace Tests\Feature\Console;

use App\Jobs\GenerateIngredientDescription;
use App\Jobs\GenerateNutrientDescription;
use App\Models\Ingredient;
use App\Models\Nutrient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateDescriptionsTest extends TestCase
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

    private function nutrient(array $attrs = []): Nutrient
    {
        return Nutrient::withoutEvents(fn () => Nutrient::factory()->create($attrs));
    }

    // =========================================================================
    // Invalid model argument
    // =========================================================================

    public function test_fails_for_unknown_model_argument(): void
    {
        $this->artisan('app:generate-descriptions foobar')->assertFailed();
        Queue::assertNothingPushed();
    }

    // =========================================================================
    // Ingredients
    // =========================================================================

    public function test_dispatches_jobs_only_for_ingredients_without_description(): void
    {
        $this->ingredient(['description' => null]);
        $this->ingredient(['description' => null]);
        $this->ingredient(['description' => 'Already has one']);

        $this->artisan('app:generate-descriptions ingredients')->assertSuccessful();

        Queue::assertPushed(GenerateIngredientDescription::class, 2);
    }

    public function test_dispatches_ingredient_jobs_to_ingredients_queue(): void
    {
        $this->ingredient(['description' => null]);

        $this->artisan('app:generate-descriptions ingredients')->assertSuccessful();

        Queue::assertPushedOn('ingredients', GenerateIngredientDescription::class);
    }

    public function test_outputs_dispatch_count_for_ingredients(): void
    {
        $this->ingredient(['description' => null]);
        $this->ingredient(['description' => null]);

        $this->artisan('app:generate-descriptions ingredients')
            ->expectsOutputToContain('2')
            ->assertSuccessful();
    }

    public function test_does_nothing_when_all_ingredients_have_descriptions(): void
    {
        $this->ingredient(['description' => 'Has one']);

        $this->artisan('app:generate-descriptions ingredients')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_ingredient_overwrite_flag_dispatches_jobs_for_all(): void
    {
        $this->ingredient(['description' => null]);
        $this->ingredient(['description' => 'Already has one']);

        $this->artisan('app:generate-descriptions ingredients --overwrite')->assertSuccessful();

        Queue::assertPushed(GenerateIngredientDescription::class, 2);
    }

    public function test_ingredient_id_flag_processes_only_specified(): void
    {
        $a = $this->ingredient(['description' => null]);
        $b = $this->ingredient(['description' => null]);
        $this->ingredient(['description' => null]);

        $this->artisan("app:generate-descriptions ingredients --id={$a->id} --id={$b->id}")->assertSuccessful();

        Queue::assertPushed(GenerateIngredientDescription::class, 2);
    }

    public function test_ingredient_id_flag_with_overwrite_regenerates_specified(): void
    {
        $a = $this->ingredient(['description' => 'Existing']);
        $this->ingredient(['description' => null]);

        $this->artisan("app:generate-descriptions ingredients --id={$a->id} --overwrite")->assertSuccessful();

        Queue::assertPushed(GenerateIngredientDescription::class, 1);
    }

    public function test_skips_soft_deleted_ingredients(): void
    {
        Ingredient::withoutEvents(function () {
            Ingredient::factory()->create(['description' => null])->delete();
        });

        $this->artisan('app:generate-descriptions ingredients')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    // =========================================================================
    // Nutrients
    // =========================================================================

    public function test_dispatches_jobs_only_for_nutrients_without_description(): void
    {
        $this->nutrient(['description' => null]);
        $this->nutrient(['description' => null]);
        $this->nutrient(['description' => 'Already has one']);

        $this->artisan('app:generate-descriptions nutrients')->assertSuccessful();

        Queue::assertPushed(GenerateNutrientDescription::class, 2);
    }

    public function test_dispatches_nutrient_jobs_to_nutrients_queue(): void
    {
        $this->nutrient(['description' => null]);

        $this->artisan('app:generate-descriptions nutrients')->assertSuccessful();

        Queue::assertPushedOn('nutrients', GenerateNutrientDescription::class);
    }

    public function test_outputs_dispatch_count_for_nutrients(): void
    {
        $this->nutrient(['description' => null]);
        $this->nutrient(['description' => null]);

        $this->artisan('app:generate-descriptions nutrients')
            ->expectsOutputToContain('2')
            ->assertSuccessful();
    }

    public function test_does_nothing_when_all_nutrients_have_descriptions(): void
    {
        $this->nutrient(['description' => 'Has one']);

        $this->artisan('app:generate-descriptions nutrients')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_nutrient_overwrite_flag_dispatches_jobs_for_all(): void
    {
        $this->nutrient(['description' => null]);
        $this->nutrient(['description' => 'Already has one']);

        $this->artisan('app:generate-descriptions nutrients --overwrite')->assertSuccessful();

        Queue::assertPushed(GenerateNutrientDescription::class, 2);
    }

    public function test_nutrient_id_flag_processes_only_specified(): void
    {
        $a = $this->nutrient(['description' => null]);
        $b = $this->nutrient(['description' => null]);
        $this->nutrient(['description' => null]);

        $this->artisan("app:generate-descriptions nutrients --id={$a->id} --id={$b->id}")->assertSuccessful();

        Queue::assertPushed(GenerateNutrientDescription::class, 2);
    }

    public function test_nutrient_id_flag_with_overwrite_regenerates_specified(): void
    {
        $a = $this->nutrient(['description' => 'Existing']);
        $this->nutrient(['description' => null]);

        $this->artisan("app:generate-descriptions nutrients --id={$a->id} --overwrite")->assertSuccessful();

        Queue::assertPushed(GenerateNutrientDescription::class, 1);
    }

    public function test_skips_soft_deleted_nutrients(): void
    {
        Nutrient::withoutEvents(function () {
            Nutrient::factory()->create(['description' => null])->delete();
        });

        $this->artisan('app:generate-descriptions nutrients')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    // =========================================================================
    // Cross-model isolation
    // =========================================================================

    public function test_ingredients_model_does_not_dispatch_nutrient_jobs(): void
    {
        $this->ingredient(['description' => null]);
        $this->nutrient(['description' => null]);

        $this->artisan('app:generate-descriptions ingredients')->assertSuccessful();

        Queue::assertPushed(GenerateIngredientDescription::class, 1);
        Queue::assertNotPushed(GenerateNutrientDescription::class);
    }

    public function test_nutrients_model_does_not_dispatch_ingredient_jobs(): void
    {
        $this->ingredient(['description' => null]);
        $this->nutrient(['description' => null]);

        $this->artisan('app:generate-descriptions nutrients')->assertSuccessful();

        Queue::assertNotPushed(GenerateIngredientDescription::class);
        Queue::assertPushed(GenerateNutrientDescription::class, 1);
    }
}
