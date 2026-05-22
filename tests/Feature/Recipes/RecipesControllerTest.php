<?php

namespace Tests\Feature\Recipes;

use App\Models\DietTag;
use App\Models\Ingredient;
use App\Models\Nutrient;
use App\Models\Recipe;
use App\Models\Unit;
use App\Services\Search\SearchServiceContract;
use App\Services\Search\SearchServiceResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\LoginTestUser;
use Tests\MakesUnit;
use Tests\TestCase;

class RecipesControllerTest extends TestCase
{
    use RefreshDatabase, LoginTestUser, MakesUnit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->login();
        Queue::fake();
        Http::fake([config('zinc.base_url') . '/*' => Http::response([], 404)]);
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_recipes(): void
    {
        Recipe::factory()->count(30)->create();

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('recipes.index'));

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('current_page', $json);
        $this->assertArrayHasKey('last_page', $json);
        $this->assertArrayHasKey('per_page', $json);
        $this->assertArrayHasKey('total', $json);
        $this->assertCount(25, $json['data']);
        $this->assertEquals(30, $json['total']);

        $page2 = $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('recipes.index') . '?page=2')
            ->json();

        $this->assertCount(5, $page2['data']);
        $this->assertEquals(2, $page2['current_page']);
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_recipe_with_relationships(): void
    {
        $recipe = Recipe::factory()->create([
            'name'        => 'Pasta Bolognese',
            'description' => 'A classic Italian dish.',
            'portions'    => 4,
        ]);
        $tag = DietTag::factory()->create();
        $recipe->dietTags()->attach($tag->id);

        $json = $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('recipes.show', $recipe))
            ->assertStatus(200)
            ->json();

        $this->assertEquals($recipe->id, $json['id']);
        $this->assertEquals('Pasta Bolognese', $json['name']);
        $this->assertEquals(4, $json['portions']);
        $this->assertArrayHasKey('diet_tags', $json);
        $this->assertArrayHasKey('ingredients', $json);
        $this->assertArrayHasKey('nutrient_profile', $json);
        $this->assertIsArray($json['nutrient_profile']);
    }

    public function test_show_includes_ingredient_pivot_with_unit(): void
    {
        $gram       = Unit::create(['name' => 'gram', 'abbreviation' => 'g', 'type' => 'mass']);
        $ingredient = Ingredient::factory()->create(['default_amount_unit_id' => $gram->id]);
        $recipe     = Recipe::factory()->create(['portions' => 2]);
        $recipe->ingredients()->attach($ingredient->id, ['amount' => 150.0, 'unit_id' => $gram->id]);

        $json = $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('recipes.show', $recipe))
            ->assertStatus(200)
            ->json();

        $this->assertCount(1, $json['ingredients']);
        $ing = $json['ingredients'][0];
        $this->assertEquals($ingredient->id, $ing['id']);
        $this->assertEquals(150.0, $ing['pivot']['amount']);
        $this->assertEquals($gram->id, $ing['pivot']['unit_id']);
        $this->assertEquals('g', $ing['pivot']['unit']['abbreviation']);
    }

    public function test_show_returns_404_for_nonexistent_recipe(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('recipes.show', 99999))
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_recipe_with_all_fields(): void
    {
        $payload = [
            'name'         => 'Chicken Tikka Masala',
            'description'  => 'A creamy, spiced chicken dish.',
            'instructions' => "## Method\n1. Marinate chicken.\n2. Cook sauce.",
            'portions'     => 4,
            'source_url'   => 'https://example.com/chicken-tikka',
        ];

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.store'), $payload)
            ->assertStatus(201);

        $this->assertDatabaseHas('recipes', ['name' => 'Chicken Tikka Masala', 'portions' => 4]);
        $this->assertArrayHasKey('id', $response->json());
        $this->assertArrayHasKey('slug', $response->json());
    }

    public function test_store_auto_generates_slug(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.store'), ['name' => 'Green Salad', 'portions' => 2])
            ->assertStatus(201)
            ->assertJsonFragment(['slug' => 'green-salad']);
    }

    public function test_store_requires_name_and_portions(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.store'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'portions']);
    }

    public function test_store_rejects_portions_less_than_one(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.store'), ['name' => 'Test', 'portions' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['portions']);
    }

    public function test_store_rejects_invalid_source_url(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.store'), [
                'name'       => 'Test',
                'portions'   => 2,
                'source_url' => 'not-a-valid-url',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['source_url']);
    }

    public function test_store_accepts_nullable_optional_fields(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.store'), ['name' => 'Minimal Recipe', 'portions' => 1])
            ->assertStatus(201);

        $recipe = Recipe::where('name', 'Minimal Recipe')->first();
        $this->assertNull($recipe->description);
        $this->assertNull($recipe->instructions);
        $this->assertNull($recipe->source_url);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_modifies_recipe(): void
    {
        $recipe = Recipe::factory()->create(['portions' => 2]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('recipes.update', $recipe), ['name' => 'Updated Name', 'portions' => 6])
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Name', 'portions' => 6]);

        $this->assertDatabaseHas('recipes', ['id' => $recipe->id, 'portions' => 6]);
    }

    public function test_update_accepts_partial_payload(): void
    {
        $recipe = Recipe::factory()->create(['portions' => 2]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('recipes.update', $recipe), ['portions' => 8])
            ->assertStatus(200)
            ->assertJsonFragment(['portions' => 8]);
    }

    public function test_update_returns_404_for_nonexistent_recipe(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('recipes.update', 99999), ['portions' => 2])
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // delete
    // -------------------------------------------------------------------------

    public function test_delete_soft_deletes_recipe(): void
    {
        $recipe = Recipe::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('recipes.delete', $recipe))
            ->assertStatus(204);

        $this->assertNull(Recipe::find($recipe->id));
        $this->assertNotNull(Recipe::withTrashed()->find($recipe->id));
    }

    public function test_delete_returns_404_for_nonexistent_recipe(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('recipes.delete', 99999))
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // nutrient-profile
    // -------------------------------------------------------------------------

    public function test_nutrient_profile_returns_computed_totals_and_per_portion(): void
    {
        $gram       = Unit::create(['name' => 'gram', 'abbreviation' => 'g', 'type' => 'mass']);
        $nutrient   = Nutrient::factory()->create(['name' => 'Protein']);
        $ingredient = Ingredient::factory()->create(['default_amount_unit_id' => $gram->id]);

        $ingredient->nutrients()->attach($nutrient->id, [
            'amount'         => 10.0, // 10 g per 100 g of ingredient
            'amount_unit_id' => $gram->id,
        ]);

        $recipe = Recipe::factory()->create(['portions' => 2]);
        $recipe->ingredients()->attach($ingredient->id, [
            'amount'  => 200.0, // 200 g → scale 2 → total 20 g, per portion 10 g
            'unit_id' => $gram->id,
        ]);

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('recipes.nutrient-profile', $recipe))
            ->assertStatus(200);

        $json = $response->json();

        $this->assertArrayHasKey('total', $json);
        $this->assertArrayHasKey('per_portion', $json);
        $this->assertArrayHasKey('portions', $json);
        $this->assertEquals(2, $json['portions']);
        $this->assertCount(1, $json['total']);
        $this->assertEquals(20.0, $json['total'][0]['amount']);
        $this->assertEquals(10.0, $json['per_portion'][0]['amount']);
        $this->assertEquals($nutrient->id, $json['total'][0]['nutrient_id']);
    }

    public function test_nutrient_profile_returns_empty_for_recipe_with_no_ingredients(): void
    {
        $recipe = Recipe::factory()->create(['portions' => 2]);

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('recipes.nutrient-profile', $recipe))
            ->assertStatus(200);

        $json = $response->json();
        $this->assertEmpty($json['total']);
        $this->assertEmpty($json['per_portion']);
    }

    // -------------------------------------------------------------------------
    // search
    // -------------------------------------------------------------------------

    public function test_search_delegates_to_search_service_and_returns_results(): void
    {
        $this->mock(SearchServiceContract::class, function ($mock) {
            $mock->shouldReceive('search')
                ->with(config('zinc.indices.recipes'), 'pasta', 25, 1)
                ->once()
                ->andReturn(new SearchServiceResponse(
                    query: 'pasta',
                    index: config('zinc.indices.recipes'),
                    total: 1,
                    perPage: 25,
                    results: [['id' => 1, 'name' => 'Pasta Bolognese', 'description' => null, 'score' => 0.9]],
                ));
        });

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.search'), ['query' => 'pasta'])
            ->assertStatus(200)
            ->assertJsonPath('results.0.name', 'Pasta Bolognese');
    }

    public function test_search_requires_query(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.search'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }

    protected function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }
}
