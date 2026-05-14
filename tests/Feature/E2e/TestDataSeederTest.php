<?php

namespace Tests\Feature\E2e;

use App\Models\Ingredient;
use App\Models\Recipe;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestDataSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_fixture_ingredients_exist_after_seeding(): void
    {
        $this->seed(TestDataSeeder::class);

        $this->assertDatabaseHas('ingredients', ['slug' => 'test-chicken-breast']);
        $this->assertDatabaseHas('ingredients', ['slug' => 'test-white-rice']);
        $this->assertDatabaseHas('ingredients', ['slug' => 'test-olive-oil']);
    }

    public function test_fixture_recipe_exists_after_seeding(): void
    {
        $this->seed(TestDataSeeder::class);

        $this->assertDatabaseHas('recipes', ['slug' => 'test-grilled-chicken-with-rice']);

        $recipe = Recipe::where('slug', 'test-grilled-chicken-with-rice')->first();
        $this->assertNotNull($recipe);
        $this->assertEquals(2, $recipe->portions);
        $this->assertCount(2, $recipe->ingredients);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(TestDataSeeder::class);
        $this->seed(TestDataSeeder::class);

        $this->assertEquals(1, Ingredient::where('slug', 'test-chicken-breast')->count());
        $this->assertEquals(1, Ingredient::where('slug', 'test-white-rice')->count());
        $this->assertEquals(1, Ingredient::where('slug', 'test-olive-oil')->count());
        $this->assertEquals(1, Recipe::where('slug', 'test-grilled-chicken-with-rice')->count());
    }
}
