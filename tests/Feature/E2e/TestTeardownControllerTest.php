<?php

namespace Tests\Feature\E2e;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\UsesTestMode;

class TestTeardownControllerTest extends TestCase
{
    use RefreshDatabase, UsesTestMode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableTestMode();
        $this->seed(DatabaseSeeder::class);
        $this->seed(TestDataSeeder::class);

        Http::fake([config('zinc.base_url') . '/*' => Http::response(['message' => 'OK'], 200)]);
    }

    public function test_returns_404_when_test_mode_disabled(): void
    {
        config(['app.test_mode' => false]);

        $this->postJson('/api/test/teardown')->assertNotFound();
    }

    public function test_drops_all_data(): void
    {
        $this->postJson('/api/test/teardown')->assertOk()->assertJson(['teardown' => true]);

        $this->assertEquals(0, \App\Models\Ingredient::count());
        $this->assertEquals(0, \App\Models\Recipe::count());
        $this->assertEquals(0, \App\Models\Unit::count());
    }

    public function test_schema_remains_intact(): void
    {
        $this->postJson('/api/test/teardown')->assertOk();

        $this->assertTrue(\Schema::hasTable('ingredients'));
        $this->assertTrue(\Schema::hasTable('recipes'));
        $this->assertTrue(\Schema::hasTable('units'));
        $this->assertTrue(\Schema::hasTable('nutrients'));
    }
}
