<?php

namespace Tests\Unit\Jobs;

use Mockery;
use Tests\TestCase;
use App\Enums\SyncStatus;
use App\Models\Ingredient;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use App\Jobs\SyncIngredientToSearch;
use App\Services\Search\SearchServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SyncIngredientToSearchTest extends TestCase
{
    use RefreshDatabase;

    protected Ingredient $ingredient;

    protected function setUp(): void
    {
        parent::setUp();
        // Bus::fake();

        // Create a test ingredient without triggering model events
        $this->ingredient = Ingredient::withoutEvents(function () {
            return Ingredient::factory()->create();
        });
    }

    public function test_handle_calls_insert_on_search_service(): void
    {
        $mock = Mockery::mock(SearchServiceContract::class);
        $mock->shouldReceive('insert')
            ->once()
            ->with(
                config('zinc.indices.ingredients'),
                $this->ingredient->id,
                Mockery::on(fn($payload) =>
                    array_key_exists('brand',               $payload) &&
                    array_key_exists('default_amount_unit', $payload) &&
                    array_key_exists('nutrients',           $payload) &&
                    array_key_exists('nutrition_facts',     $payload) &&
                    array_key_exists('categories',          $payload)
                )
            );

        $job = new SyncIngredientToSearch($this->ingredient, 'insert');
        $job->handle($mock);
        $this->assertTrue(true);
    }

    public function test_handle_calls_update_on_search_service(): void
    {
        $mock = Mockery::mock(SearchServiceContract::class);
        $mock->shouldReceive('update')
            ->once()
            ->with(
                config('zinc.indices.ingredients'),
                $this->ingredient->id,
                Mockery::on(fn($payload) =>
                    array_key_exists('brand',               $payload) &&
                    array_key_exists('default_amount_unit', $payload) &&
                    array_key_exists('nutrients',           $payload) &&
                    array_key_exists('nutrition_facts',     $payload) &&
                    array_key_exists('categories',          $payload)
                )
            );

        $job = new SyncIngredientToSearch($this->ingredient, 'update');
        $job->handle($mock);
        $this->assertTrue(true);
    }

    public function test_handle_calls_delete_on_search_service(): void
    {
        $mock = Mockery::mock(SearchServiceContract::class);
        $mock->shouldReceive('delete')
            ->once()
            ->with(config('zinc.indices.ingredients'), $this->ingredient->id);

        $job = new SyncIngredientToSearch($this->ingredient, 'delete');
        $job->handle($mock);
        $this->assertTrue(true);
    }

    public function test_handle_sets_sync_status_to_synced_on_insert(): void
    {
        $mock = Mockery::mock(SearchServiceContract::class);
        $mock->shouldReceive('insert')->once();

        $job = new SyncIngredientToSearch($this->ingredient, 'insert');
        $job->handle($mock);

        $this->assertEquals(SyncStatus::Synced, $this->ingredient->fresh()->sync_status);
    }

    public function test_handle_sets_sync_status_to_synced_on_update(): void
    {
        $mock = Mockery::mock(SearchServiceContract::class);
        $mock->shouldReceive('update')->once();

        $job = new SyncIngredientToSearch($this->ingredient, 'update');
        $job->handle($mock);

        $this->assertEquals(SyncStatus::Synced, $this->ingredient->fresh()->sync_status);
    }

    public function test_handle_sets_sync_status_to_synced_on_delete(): void
    {
        $mock = Mockery::mock(SearchServiceContract::class);
        $mock->shouldReceive('delete')->once();

        $job = new SyncIngredientToSearch((object)['id' => $this->ingredient->id], 'delete');
        $job->handle($mock);

        $status = DB::table('ingredients')->where('id', $this->ingredient->id)->value('sync_status');
        $this->assertEquals(SyncStatus::Synced->value, $status);
    }

    public function test_failed_sets_sync_status_to_failed(): void
    {
        $job = new SyncIngredientToSearch($this->ingredient, 'insert');
        $job->failed(new \Exception('Search unavailable'));

        $this->assertEquals(SyncStatus::Failed, $this->ingredient->fresh()->sync_status);
    }

    public function test_failed_sets_sync_status_to_failed_for_delete_action(): void
    {
        $job = new SyncIngredientToSearch((object)['id' => $this->ingredient->id], 'delete');
        $job->failed(new \Exception('Search unavailable'));

        $status = DB::table('ingredients')->where('id', $this->ingredient->id)->value('sync_status');
        $this->assertEquals(SyncStatus::Failed->value, $status);
    }

    public function test_handle_skips_search_but_sets_synced_when_ingredient_not_found(): void
    {
        $mock = Mockery::mock(SearchServiceContract::class);
        $mock->shouldNotReceive('insert');
        $mock->shouldNotReceive('update');

        Ingredient::withoutEvents(fn() => $this->ingredient->delete());

        $job = new SyncIngredientToSearch((object)['id' => $this->ingredient->id], 'insert');
        $job->handle($mock);

        $status = DB::table('ingredients')->where('id', $this->ingredient->id)->value('sync_status');
        $this->assertEquals(SyncStatus::Synced->value, $status);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
