<?php

namespace Tests\Unit\Console;

use App\Enums\SyncStatus;
use App\Jobs\SyncIngredientToSearch;
use App\Jobs\SyncNutrientToSearch;
use App\Models\Ingredient;
use App\Models\Nutrient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueuePendingSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function makeIngredient(SyncStatus $status = SyncStatus::Pending): Ingredient
    {
        $ingredient = Ingredient::withoutEvents(fn() => Ingredient::factory()->create());
        if ($status !== SyncStatus::Pending) {
            DB::table('ingredients')->where('id', $ingredient->id)->update(['sync_status' => $status->value]);
        }
        return $ingredient;
    }

    private function makeNutrient(SyncStatus $status = SyncStatus::Pending): Nutrient
    {
        $nutrient = Nutrient::withoutEvents(fn() => Nutrient::factory()->create());
        if ($status !== SyncStatus::Pending) {
            DB::table('nutrients')->where('id', $nutrient->id)->update(['sync_status' => $status->value]);
        }
        return $nutrient;
    }

    public function test_dispatches_insert_jobs_for_pending_ingredients(): void
    {
        $pending = $this->makeIngredient(SyncStatus::Pending);
        $this->makeIngredient(SyncStatus::Synced);

        $this->artisan('app:queue-pending-sync', ['--model' => 'ingredients'])
            ->assertExitCode(0);

        Queue::assertPushed(SyncIngredientToSearch::class, 1);
        Queue::assertPushed(SyncIngredientToSearch::class, fn($job) =>
            $job->id === $pending->id && $job->action === 'insert'
        );
    }

    public function test_dispatches_insert_jobs_for_pending_nutrients(): void
    {
        $pending = $this->makeNutrient(SyncStatus::Pending);
        $this->makeNutrient(SyncStatus::Synced);

        $this->artisan('app:queue-pending-sync', ['--model' => 'nutrients'])
            ->assertExitCode(0);

        Queue::assertPushed(SyncNutrientToSearch::class, 1);
        Queue::assertPushed(SyncNutrientToSearch::class, fn($job) =>
            $job->nutrient->id === $pending->id && $job->action === 'insert'
        );
    }

    public function test_model_option_ingredients_skips_nutrients(): void
    {
        $this->makeIngredient();
        $this->makeNutrient();

        $this->artisan('app:queue-pending-sync', ['--model' => 'ingredients'])
            ->assertExitCode(0);

        Queue::assertPushed(SyncIngredientToSearch::class, 1);
        Queue::assertNotPushed(SyncNutrientToSearch::class);
    }

    public function test_model_option_nutrients_skips_ingredients(): void
    {
        $this->makeIngredient();
        $this->makeNutrient();

        $this->artisan('app:queue-pending-sync', ['--model' => 'nutrients'])
            ->assertExitCode(0);

        Queue::assertNotPushed(SyncIngredientToSearch::class);
        Queue::assertPushed(SyncNutrientToSearch::class, 1);
    }

    public function test_without_model_option_processes_both(): void
    {
        $this->makeIngredient();
        $this->makeNutrient();

        $this->artisan('app:queue-pending-sync')
            ->assertExitCode(0);

        Queue::assertPushed(SyncIngredientToSearch::class, 1);
        Queue::assertPushed(SyncNutrientToSearch::class, 1);
    }

    public function test_include_failed_also_queues_failed_records(): void
    {
        $this->makeIngredient(SyncStatus::Pending);
        $this->makeIngredient(SyncStatus::Failed);
        $this->makeIngredient(SyncStatus::Synced);

        $this->artisan('app:queue-pending-sync', ['--model' => 'ingredients', '--include-failed' => true])
            ->assertExitCode(0);

        Queue::assertPushed(SyncIngredientToSearch::class, 2);
    }

    public function test_without_include_failed_skips_failed_records(): void
    {
        $this->makeIngredient(SyncStatus::Failed);

        $this->artisan('app:queue-pending-sync', ['--model' => 'ingredients'])
            ->assertExitCode(0);

        Queue::assertNotPushed(SyncIngredientToSearch::class);
    }

    public function test_ingredient_jobs_are_dispatched_on_ingredients_queue(): void
    {
        $this->makeIngredient();

        $this->artisan('app:queue-pending-sync', ['--model' => 'ingredients'])
            ->assertExitCode(0);

        Queue::assertPushed(SyncIngredientToSearch::class, fn($job) => $job->queue === 'ingredients');
    }

    public function test_nutrient_jobs_are_dispatched_on_nutrients_queue(): void
    {
        $this->makeNutrient();

        $this->artisan('app:queue-pending-sync', ['--model' => 'nutrients'])
            ->assertExitCode(0);

        Queue::assertPushed(SyncNutrientToSearch::class, fn($job) => $job->queue === 'nutrients');
    }

    public function test_exits_successfully_when_no_pending_records(): void
    {
        $this->artisan('app:queue-pending-sync')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_soft_deleted_ingredient_with_pending_sync_status_is_not_queued(): void
    {
        $ingredient = Ingredient::withoutEvents(fn() => Ingredient::factory()->create());
        Ingredient::withoutEvents(fn() => $ingredient->delete());

        $this->artisan('app:queue-pending-sync', ['--model' => 'ingredients'])
            ->assertExitCode(0);

        Queue::assertNotPushed(SyncIngredientToSearch::class);
    }

    public function test_fails_for_invalid_model_option(): void
    {
        $this->artisan('app:queue-pending-sync', ['--model' => 'invalid'])
            ->assertExitCode(1);

        Queue::assertNothingPushed();
    }
}
