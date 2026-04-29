<?php

namespace Tests\Unit\Jobs;

use Mockery;
use Tests\TestCase;
use App\Models\Nutrient;
use App\Jobs\SyncNutrientToSearch;
use App\Services\Search\SearchServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

class SyncNutrientToSearchTest extends TestCase
{
    use RefreshDatabase;

    protected Nutrient $nutrient;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $this->nutrient = Nutrient::withoutEvents(function() {
            return Nutrient::factory()->create();
        });
    }

    public function test_handle_calls_insert_on_search_service(): void
    {
        $mock = Mockery::mock(SearchServiceContract::class);
        $mock->shouldReceive('insert')
            ->once()
            ->with(
                config('zinc.indices.nutrients'),
                $this->nutrient->id,
                Mockery::on(fn($payload) =>
                    array_key_exists('source',         $payload) &&
                    array_key_exists('canonical_unit', $payload) &&
                    array_key_exists('parent',         $payload) &&
                    array_key_exists('children',       $payload) &&
                    array_key_exists('tags',           $payload)
                )
            );

        $job = new SyncNutrientToSearch($this->nutrient, 'insert');
        $job->handle($mock);
        $this->assertTrue(true);
    }

    public function test_handle_calls_update_on_search_service(): void
    {
        $mock = Mockery::mock(SearchServiceContract::class);
        $mock->shouldReceive('update')
            ->once()
            ->with(
                config('zinc.indices.nutrients'),
                $this->nutrient->id,
                Mockery::on(fn($payload) =>
                    array_key_exists('source',         $payload) &&
                    array_key_exists('canonical_unit', $payload) &&
                    array_key_exists('parent',         $payload) &&
                    array_key_exists('children',       $payload) &&
                    array_key_exists('tags',           $payload)
                )
            );

        $job = new SyncNutrientToSearch($this->nutrient, 'update');
        $job->handle($mock);
        $this->assertTrue(true);
    }

    /* This test will throw: ! handle calls delete on search service → This
     * test did not perform any assertions. This is normal because
     * the assertion is performed inside Mockery.
    */
    public function test_handle_calls_delete_on_search_service(): void
    {
        $mock = Mockery::mock(SearchServiceContract::class);
        $mock->shouldReceive('delete')
            ->once()
            ->with(config('zinc.indices.nutrients'), $this->nutrient->id);

        $job = new SyncNutrientToSearch($this->nutrient, 'delete');
        $job->handle($mock);
        $this->assertTrue(true);
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
