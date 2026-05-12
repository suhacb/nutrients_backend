<?php

namespace Tests\Feature\DietTags;

use App\Models\DietTag;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\LoginTestUser;
use Tests\TestCase;

class DietTagsControllerTest extends TestCase
{
    use RefreshDatabase, LoginTestUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->login();
        Queue::fake();
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_diet_tags(): void
    {
        DietTag::factory()->count(30)->create();

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('diet-tags.index'));

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
            ->getJson(route('diet-tags.index') . '?page=2')
            ->json();

        $this->assertCount(5, $page2['data']);
        $this->assertEquals(2, $page2['current_page']);
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_single_diet_tag(): void
    {
        $tag = DietTag::factory()->create([
            'name'        => 'Ketogenic',
            'slug'        => 'ketogenic',
            'description' => 'High fat, very low carbohydrate diet.',
        ]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('diet-tags.show', $tag))
            ->assertStatus(200)
            ->assertJson([
                'id'          => $tag->id,
                'name'        => $tag->name,
                'slug'        => $tag->slug,
                'description' => $tag->description,
            ]);
    }

    public function test_show_returns_404_for_nonexistent_tag(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('diet-tags.show', 99999))
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_diet_tag_with_all_fields(): void
    {
        $payload = [
            'name'        => 'Vegan',
            'slug'        => 'vegan',
            'description' => 'Excludes all animal products.',
        ];

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('diet-tags.store'), $payload)
            ->assertStatus(201)
            ->assertJson($payload);

        $this->assertDatabaseHas('diet_tags', ['slug' => 'vegan']);
    }

    public function test_store_auto_generates_slug_when_omitted(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('diet-tags.store'), ['name' => 'Low Carb'])
            ->assertStatus(201)
            ->assertJsonFragment(['slug' => 'low-carb']);

        $this->assertDatabaseHas('diet_tags', ['slug' => 'low-carb']);
    }

    public function test_store_accepts_null_description(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('diet-tags.store'), ['name' => 'Paleo', 'slug' => 'paleo'])
            ->assertStatus(201);

        $this->assertNull(DietTag::where('slug', 'paleo')->first()->description);
    }

    public function test_store_requires_name(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('diet-tags.store'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_rejects_duplicate_slug(): void
    {
        DietTag::factory()->create(['slug' => 'vegan']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('diet-tags.store'), ['name' => 'Vegan Duplicate', 'slug' => 'vegan'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_store_rejects_non_string_description(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('diet-tags.store'), [
                'name'        => 'Valid Name',
                'slug'        => 'valid-slug',
                'description' => ['not', 'a', 'string'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_modifies_diet_tag(): void
    {
        $tag = DietTag::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('diet-tags.update', $tag), ['name' => 'New Name', 'slug' => 'new-name'])
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'New Name', 'slug' => 'new-name']);

        $this->assertDatabaseHas('diet_tags', ['id' => $tag->id, 'name' => 'New Name']);
    }

    public function test_update_allows_same_slug_on_same_record(): void
    {
        $tag = DietTag::factory()->create(['name' => 'Vegan', 'slug' => 'vegan']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('diet-tags.update', $tag), ['name' => 'Vegan Updated', 'slug' => 'vegan'])
            ->assertStatus(200);
    }

    public function test_update_accepts_empty_payload(): void
    {
        $tag = DietTag::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('diet-tags.update', $tag), [])
            ->assertStatus(200);
    }

    public function test_update_rejects_duplicate_slug(): void
    {
        DietTag::factory()->create(['slug' => 'vegan']);
        $tag = DietTag::factory()->create(['slug' => 'keto']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('diet-tags.update', $tag), ['slug' => 'vegan'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_update_returns_404_for_nonexistent_tag(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('diet-tags.update', 99999), ['name' => 'New Name'])
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // delete
    // -------------------------------------------------------------------------

    public function test_delete_removes_diet_tag(): void
    {
        $tag = DietTag::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('diet-tags.delete', $tag))
            ->assertStatus(204);

        $this->assertDatabaseMissing('diet_tags', ['id' => $tag->id]);
    }

    public function test_delete_with_attached_recipes_removes_pivot_rows(): void
    {
        $tag    = DietTag::factory()->create();
        $recipe = Recipe::factory()->create();
        $recipe->dietTags()->attach($tag->id);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('diet-tags.delete', $tag))
            ->assertStatus(204);

        $this->assertDatabaseMissing('diet_tags', ['id' => $tag->id]);
        $this->assertDatabaseCount('recipe_diet_tag', 0);
    }

    public function test_delete_returns_404_for_nonexistent_tag(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('diet-tags.delete', 99999))
            ->assertStatus(404);
    }

    protected function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }
}
