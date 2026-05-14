<?php

namespace Tests\Feature\E2e;

use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\Nutrient;
use App\Models\Recipe;
use App\Models\Unit;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\UsesTestMode;

class TestResetControllerTest extends TestCase
{
    use RefreshDatabase, UsesTestMode;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->enableTestMode();
        $this->seed(DatabaseSeeder::class);
        $this->seed(TestDataSeeder::class);

        Http::fake([config('zinc.base_url') . '/*' => Http::response(['message' => 'OK'], 200)]);
    }

    public function test_returns_404_when_test_mode_disabled(): void
    {
        config(['app.test_mode' => false]);

        $this->postJson('/api/test/reset')->assertNotFound();
    }

    public function test_deletes_non_fixture_records(): void
    {
        $unit = Unit::where('abbreviation', 'g')->first();
        Ingredient::forceCreate([
            'name'                   => 'Non Fixture Ingredient',
            'slug'                   => 'non-fixture-ingredient',
            'source'                 => 'test',
            'class'                  => 'final',
            'default_amount'         => 100,
            'default_amount_unit_id' => $unit->id,
        ]);

        $this->postJson('/api/test/reset')->assertOk()->assertJson(['reset' => true]);

        $this->assertDatabaseMissing('ingredients', ['slug' => 'non-fixture-ingredient']);
    }

    public function test_preserves_fixture_records(): void
    {
        $this->postJson('/api/test/reset')->assertOk();

        $this->assertDatabaseHas('ingredients', ['slug' => 'test-chicken-breast']);
        $this->assertDatabaseHas('ingredients', ['slug' => 'test-white-rice']);
        $this->assertDatabaseHas('ingredients', ['slug' => 'test-olive-oil']);
        $this->assertDatabaseHas('recipes', ['slug' => 'test-grilled-chicken-with-rice']);
    }

    public function test_reseeds_deleted_fixtures(): void
    {
        // Simulate the real e2e scenario: a fixture is soft-deleted via the API during a test.
        Ingredient::where('slug', 'test-chicken-breast')->delete();

        $this->postJson('/api/test/reset')->assertOk();

        $this->assertDatabaseHas('ingredients', ['slug' => 'test-chicken-breast', 'deleted_at' => null]);
    }

    public function test_deletes_non_fixture_brands(): void
    {
        Brand::forceCreate([
            'name'    => 'Non Fixture Brand',
            'slug'    => 'non-fixture-brand',
            'owner'   => 'Someone',
            'country' => 'US',
        ]);

        $this->postJson('/api/test/reset')->assertOk();

        $this->assertDatabaseMissing('brands', ['slug' => 'non-fixture-brand']);
    }

    public function test_preserves_fixture_brands(): void
    {
        $this->postJson('/api/test/reset')->assertOk();

        $this->assertDatabaseHas('brands', ['slug' => 'test-nature-fresh']);
        $this->assertDatabaseHas('brands', ['slug' => 'test-golden-harvest']);
        $this->assertDatabaseHas('brands', ['slug' => 'test-artisan-kitchen']);
    }

    public function test_restores_soft_deleted_fixture_brands(): void
    {
        Brand::where('slug', 'test-nature-fresh')->delete();

        $this->postJson('/api/test/reset')->assertOk();

        $this->assertDatabaseHas('brands', ['slug' => 'test-nature-fresh', 'deleted_at' => null]);
    }

    public function test_restores_fixture_nutrient_relationships(): void
    {
        $chicken = Ingredient::where('slug', 'test-chicken-breast')->first();
        $chicken->nutrients()->detach();

        $this->postJson('/api/test/reset')->assertOk();

        $chicken->refresh();
        $this->assertGreaterThan(0, $chicken->nutrients()->count());
    }
}
