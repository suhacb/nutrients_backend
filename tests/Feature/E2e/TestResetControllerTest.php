<?php

namespace Tests\Feature\E2e;

use App\Models\Ingredient;
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
}
