<?php

namespace Tests\Unit\Jobs;

use Mockery;
use Tests\TestCase;
use App\Models\Ingredient;
use Illuminate\Support\Facades\Bus;
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
