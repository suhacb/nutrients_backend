<?php

namespace Tests\Unit\Jobs;

use App\Enums\SyncStatus;
use App\Jobs\SyncRecipeToSearch;
use App\Models\Recipe;
use App\Services\Search\SearchServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class SyncRecipeToSearchTest extends TestCase
{
    use RefreshDatabase;

    protected Recipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recipe = Recipe::withoutEvents(function () {
            return Recipe::factory()->create();
        });
    }

    public function test_handle_calls_insert_on_search_service(): void
    {
        $mock = Mockery::mock(SearchServiceContract::class);
        $mock->shouldReceive('insert')
            ->once()
            ->with(
                config('zinc.indices.recipes'),
                $this->recipe->id,
                Mockery::on(fn($payload) =>
                    array_key_exists('diet_tags',        $payload) &&
                    array_key_exists('ingredients',      $payload) &&
                    array_key_exists('nutrient_profile', $payload)
                )
            );

        $job = new SyncRecipeToSearch($this->recipe, 'insert');
        $job->handle($mock);
        $this->assertTrue(true);
    }

    public function test_handle_calls_update_on_search_service(): void
    {
        $mock = Mockery::mock(SearchServiceContract::class);
        $mock->shouldReceive('update')
            ->once()
            ->with(
                config('zinc.indices.recipes'),
                $this->recipe->id,
                Mockery::on(fn($payload) =>
                    array_key_exists('diet_tags',        $payload) &&
                    array_key_exists('ingredients',      $payload) &&
                    array_key_exists('nutrient_profile', $payload)
                )
            );

        $job = new SyncRecipeToSearch($this->recipe, 'update');
        $job->handle($mock);
        $this->assertTrue(true);
    }

    public function test_handle_calls_delete_on_search_service(): void
    {
        $mock = Mockery::mock(SearchServiceContract::class);
        $mock->shouldReceive('delete')
            ->once()
            ->with(config('zinc.indices.recipes'), $this->recipe->id);

        $job = new SyncRecipeToSearch((object) ['id' => $this->recipe->id], 'delete');
        $job->handle($mock);
        $this->assertTrue(true);
    }

    public function test_handle_sets_sync_status_to_synced_on_insert(): void
    {
        $mock = Mockery::mock(SearchServiceContract::class);
        $mock->shouldReceive('insert')->once();

        (new SyncRecipeToSearch($this->recipe, 'insert'))->handle($mock);

        $this->assertEquals(SyncStatus::Synced, $this->recipe->fresh()->sync_status);
    }

    public function test_handle_sets_sync_status_to_synced_on_update(): void
    {
        $mock = Mockery::mock(SearchServiceContract::class);
        $mock->shouldReceive('update')->once();

        (new SyncRecipeToSearch($this->recipe, 'update'))->handle($mock);

        $this->assertEquals(SyncStatus::Synced, $this->recipe->fresh()->sync_status);
    }

    public function test_handle_sets_sync_status_to_synced_on_delete(): void
    {
        $mock = Mockery::mock(SearchServiceContract::class);
        $mock->shouldReceive('delete')->once();

        (new SyncRecipeToSearch((object) ['id' => $this->recipe->id], 'delete'))->handle($mock);

        $status = DB::table('recipes')->where('id', $this->recipe->id)->value('sync_status');
        $this->assertEquals(SyncStatus::Synced->value, $status);
    }

    public function test_failed_sets_sync_status_to_failed(): void
    {
        (new SyncRecipeToSearch($this->recipe, 'insert'))->failed(new \Exception('Search unavailable'));

        $this->assertEquals(SyncStatus::Failed, $this->recipe->fresh()->sync_status);
    }

    public function test_failed_sets_sync_status_to_failed_for_delete_action(): void
    {
        (new SyncRecipeToSearch((object) ['id' => $this->recipe->id], 'delete'))->failed(new \Exception('Search unavailable'));

        $status = DB::table('recipes')->where('id', $this->recipe->id)->value('sync_status');
        $this->assertEquals(SyncStatus::Failed->value, $status);
    }

    public function test_handle_skips_search_but_sets_synced_when_recipe_not_found(): void
    {
        $mock = Mockery::mock(SearchServiceContract::class);
        $mock->shouldNotReceive('insert');
        $mock->shouldNotReceive('update');

        Recipe::withoutEvents(fn() => $this->recipe->forceDelete());

        (new SyncRecipeToSearch((object) ['id' => $this->recipe->id], 'insert'))->handle($mock);

        $status = DB::table('recipes')->where('id', $this->recipe->id)->value('sync_status');
        $this->assertNull($status);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
