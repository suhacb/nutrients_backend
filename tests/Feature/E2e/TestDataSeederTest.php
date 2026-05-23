<?php

namespace Tests\Feature\E2e;

use App\Models\Brand;
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

    public function test_fixture_brands_exist_after_seeding(): void
    {
        $this->seed(TestDataSeeder::class);

        $this->assertDatabaseHas('brands', ['slug' => 'test-nature-fresh']);
        $this->assertDatabaseHas('brands', ['slug' => 'test-golden-harvest']);
        $this->assertDatabaseHas('brands', ['slug' => 'test-artisan-kitchen']);
    }

    public function test_fixture_ingredients_are_assigned_brands(): void
    {
        $this->seed(TestDataSeeder::class);

        $chicken = Ingredient::where('slug', 'test-chicken-breast')->first();
        $this->assertNotNull($chicken->brand_id);
        $this->assertEquals('test-nature-fresh', $chicken->brand->slug);

        $rice = Ingredient::where('slug', 'test-white-rice')->first();
        $this->assertNotNull($rice->brand_id);
        $this->assertEquals('test-golden-harvest', $rice->brand->slug);

        $oil = Ingredient::where('slug', 'test-olive-oil')->first();
        $this->assertNull($oil->brand_id);
    }

    public function test_fixture_ingredients_have_nutrients(): void
    {
        $this->seed(TestDataSeeder::class);

        $chicken = Ingredient::where('slug', 'test-chicken-breast')->first();
        $chickenNutrientNames = $chicken->nutrients->pluck('name');
        $this->assertContains('Protein', $chickenNutrientNames);
        $this->assertContains('Fat', $chickenNutrientNames);
        $this->assertContains('Energy', $chickenNutrientNames);

        $rice = Ingredient::where('slug', 'test-white-rice')->first();
        $riceNutrientNames = $rice->nutrients->pluck('name');
        $this->assertContains('Carbohydrates', $riceNutrientNames);
        $this->assertContains('Protein', $riceNutrientNames);
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
        $this->assertEquals(1, Brand::where('slug', 'test-nature-fresh')->count());
        $this->assertEquals(1, Brand::where('slug', 'test-golden-harvest')->count());
        $this->assertEquals(1, Brand::where('slug', 'test-artisan-kitchen')->count());

        $chicken = Ingredient::where('slug', 'test-chicken-breast')->first();
        $this->assertEquals(3, $chicken->nutrients()->count());

        $rice = Ingredient::where('slug', 'test-white-rice')->first();
        $this->assertEquals(2, $rice->nutrients()->count());
    }
}
