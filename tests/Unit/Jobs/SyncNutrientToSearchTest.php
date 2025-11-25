<?php

namespace Tests\Unit\Jobs;

use Mockery;
use Tests\TestCase;
use App\Models\Nutrient;
use App\Jobs\SyncNutrientToSearch;
use App\Services\Search\SearchServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SyncNutrientToSearchTest extends TestCase
{
    use RefreshDatabase;

    protected Nutrient $nutrient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nutrient = Nutrient::factory()->create();
    }

    /* This test will throw: ! handle calls insert on search service → This
     * test did not perform any assertions. This is normal because
     * the assertion is performed inside Mockery.
    */
    public function test_handle_calls_insert_on_search_service(): void
    {
        $mock = Mockery::mock(SearchServiceContract::class);
        $mock->shouldReceive('insert')
            ->once()
            ->with('nutrients', $this->nutrient->toArray());

        $job = new SyncNutrientToSearch($this->nutrient, 'insert');
        $job->handle($mock);

    }

    /* This test will throw: ! handle calls update on search service → This
     * test did not perform any assertions. This is normal because
     * the assertion is performed inside Mockery.
    */
    public function test_handle_calls_update_on_search_service(): void
    {
        $mock = Mockery::mock(SearchServiceContract::class);
        $mock->shouldReceive('update')
            ->once()
            ->with('nutrients', $this->nutrient->id, $this->nutrient->toArray());

        $job = new SyncNutrientToSearch($this->nutrient, 'update');
        $job->handle($mock);
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
            ->with('nutrients', $this->nutrient->id);

        $job = new SyncNutrientToSearch($this->nutrient, 'delete');
        $job->handle($mock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
