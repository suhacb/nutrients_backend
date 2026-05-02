<?php

namespace Tests\Feature\Console;

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

    public function test_outputs_queued_ingredient_count(): void
    {
        $this->makeIngredient();
        $this->makeIngredient();

        $this->artisan('app:queue-pending-sync', ['--model' => 'ingredients'])
            ->expectsOutputToContain('Queued 2 ingredient(s) for sync.')
            ->assertExitCode(0);
    }

    public function test_outputs_queued_nutrient_count(): void
    {
        $this->makeNutrient();
        $this->makeNutrient();
        $this->makeNutrient();

        $this->artisan('app:queue-pending-sync', ['--model' => 'nutrients'])
            ->expectsOutputToContain('Queued 3 nutrient(s) for sync.')
            ->assertExitCode(0);
    }

    public function test_outputs_both_counts_without_model_option(): void
    {
        $this->makeIngredient();
        $this->makeIngredient();
        $this->makeNutrient();

        $this->artisan('app:queue-pending-sync')
            ->expectsOutputToContain('Queued 2 ingredient(s) for sync.')
            ->expectsOutputToContain('Queued 1 nutrient(s) for sync.')
            ->assertExitCode(0);
    }

    public function test_outputs_nothing_to_queue_when_no_pending_records(): void
    {
        $this->makeIngredient(SyncStatus::Synced);
        $this->makeNutrient(SyncStatus::Synced);

        $this->artisan('app:queue-pending-sync')
            ->expectsOutputToContain('No pending records found.')
            ->assertExitCode(0);
    }

    public function test_include_failed_also_queues_failed_nutrients(): void
    {
        $this->makeNutrient(SyncStatus::Pending);
        $this->makeNutrient(SyncStatus::Failed);
        $this->makeNutrient(SyncStatus::Synced);

        $this->artisan('app:queue-pending-sync', ['--model' => 'nutrients', '--include-failed' => true])
            ->expectsOutputToContain('Queued 2 nutrient(s) for sync.')
            ->assertExitCode(0);

        Queue::assertPushed(SyncNutrientToSearch::class, 2);
    }

}
