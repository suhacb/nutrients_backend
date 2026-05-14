<?php

namespace Tests\Feature\E2e;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\UsesTestMode;

class TestSetupControllerTest extends TestCase
{
    use RefreshDatabase, UsesTestMode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableTestMode();

        Http::fake([config('zinc.base_url') . '/*' => Http::response(['message' => 'OK'], 200)]);
    }

    public function test_returns_404_when_test_mode_disabled(): void
    {
        config(['app.test_mode' => false]);

        $this->postJson('/api/test/setup')->assertNotFound();
    }

    public function test_runs_migrations(): void
    {
        $this->postJson('/api/test/setup')->assertOk()->assertJson(['setup' => true]);

        $this->assertTrue(\Schema::hasTable('ingredients'));
        $this->assertTrue(\Schema::hasTable('nutrients'));
        $this->assertTrue(\Schema::hasTable('units'));
    }

    public function test_seeds_base_data(): void
    {
        $this->postJson('/api/test/setup')->assertOk();

        $this->assertDatabaseHas('units', ['abbreviation' => 'g']);
        $this->assertGreaterThan(0, \App\Models\Nutrient::count());
    }

    public function test_seeds_fixture_data(): void
    {
        $this->postJson('/api/test/setup')->assertOk();

        $this->assertDatabaseHas('ingredients', ['slug' => 'test-chicken-breast']);
        $this->assertDatabaseHas('ingredients', ['slug' => 'test-white-rice']);
        $this->assertDatabaseHas('ingredients', ['slug' => 'test-olive-oil']);
        $this->assertDatabaseHas('recipes', ['slug' => 'test-grilled-chicken-with-rice']);
    }

    public function test_is_idempotent(): void
    {
        $this->postJson('/api/test/setup')->assertOk();
        $this->postJson('/api/test/setup')->assertOk();

        $this->assertEquals(1, Ingredient::where('slug', 'test-chicken-breast')->count());
        $this->assertEquals(1, Recipe::where('slug', 'test-grilled-chicken-with-rice')->count());
    }
}
