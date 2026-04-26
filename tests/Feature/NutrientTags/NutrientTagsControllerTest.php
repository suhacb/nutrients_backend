<?php

namespace Tests\Feature\NutrientTags;

use App\Models\Nutrient;
use App\Models\NutrientTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\LoginTestUser;
use Tests\TestCase;

class NutrientTagsControllerTest extends TestCase
{
    use RefreshDatabase, LoginTestUser;

    protected Nutrient $nutrient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->login();
        Queue::fake();
        $this->nutrient = Nutrient::factory()->create();
    }

    public function test_index_returns_paginated_nutrient_tags(): void
    {
        NutrientTag::factory()->count(30)->create();

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('nutrient-tags.index'));

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
            ->getJson(route('nutrient-tags.index') . '?page=2')
            ->json();

        $this->assertCount(5, $page2['data']);
        $this->assertEquals(2, $page2['current_page']);
    }

    public function test_show_returns_single_nutrient_tag(): void
    {
        $tag = NutrientTag::factory()->create([
            'name'        => 'Electrolyte',
            'slug'        => 'electrolyte',
            'description' => 'Minerals that carry an electric charge.',
        ]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('nutrient-tags.show', $tag))
            ->assertStatus(200)
            ->assertJson([
                'id'          => $tag->id,
                'name'        => $tag->name,
                'slug'        => $tag->slug,
                'description' => $tag->description,
            ]);
    }

    public function test_store_creates_nutrient_tag(): void
    {
        $payload = [
            'name'        => 'Electrolyte',
            'slug'        => 'electrolyte',
            'description' => 'Minerals that carry an electric charge.',
        ];

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('nutrient-tags.store'), $payload)
            ->assertStatus(201)
            ->assertJson($payload);

        $this->assertDatabaseHas('nutrient_tags', ['slug' => 'electrolyte']);
    }

    public function test_store_accepts_optional_description_omitted(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('nutrient-tags.store'), ['name' => 'Mineral', 'slug' => 'mineral'])
            ->assertStatus(201);

        $this->assertNull(NutrientTag::where('slug', 'mineral')->first()->description);
    }

    public function test_store_requires_name_and_slug(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('nutrient-tags.store'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'slug']);
    }

    public function test_store_rejects_duplicate_slug(): void
    {
        NutrientTag::factory()->create(['slug' => 'mineral']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('nutrient-tags.store'), ['name' => 'Mineral Duplicate', 'slug' => 'mineral'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_store_rejects_name_exceeding_max_length(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('nutrient-tags.store'), [
                'name' => str_repeat('a', 256),
                'slug' => 'valid-slug',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_rejects_slug_exceeding_max_length(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('nutrient-tags.store'), [
                'name' => 'Valid Name',
                'slug' => str_repeat('a', 256),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_store_rejects_non_string_name(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('nutrient-tags.store'), [
                'name' => ['not', 'a', 'string'],
                'slug' => 'valid-slug',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_rejects_non_string_slug(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('nutrient-tags.store'), [
                'name' => 'Valid Name',
                'slug' => ['not', 'a', 'string'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_store_rejects_non_string_description(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('nutrient-tags.store'), [
                'name'        => 'Valid Name',
                'slug'        => 'valid-slug',
                'description' => ['not', 'a', 'string'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    public function test_update_modifies_nutrient_tag(): void
    {
        $tag = NutrientTag::factory()->create(['name' => 'Old Name', 'slug' => 'old-slug']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('nutrient-tags.update', $tag), ['name' => 'New Name', 'slug' => 'new-slug'])
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'New Name', 'slug' => 'new-slug']);

        $this->assertDatabaseHas('nutrient_tags', ['id' => $tag->id, 'name' => 'New Name', 'slug' => 'new-slug']);
    }

    public function test_update_allows_same_slug_on_same_record(): void
    {
        $tag = NutrientTag::factory()->create(['name' => 'Mineral', 'slug' => 'mineral']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('nutrient-tags.update', $tag), ['name' => 'Mineral Updated', 'slug' => 'mineral'])
            ->assertStatus(200);
    }

    public function test_update_accepts_empty_payload(): void
    {
        $tag = NutrientTag::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('nutrient-tags.update', $tag), [])
            ->assertStatus(200);
    }

    public function test_update_rejects_duplicate_slug(): void
    {
        NutrientTag::factory()->create(['slug' => 'mineral']);
        $tag = NutrientTag::factory()->create(['slug' => 'vitamin']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('nutrient-tags.update', $tag), ['slug' => 'mineral'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_update_rejects_name_exceeding_max_length(): void
    {
        $tag = NutrientTag::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('nutrient-tags.update', $tag), ['name' => str_repeat('a', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_rejects_slug_exceeding_max_length(): void
    {
        $tag = NutrientTag::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('nutrient-tags.update', $tag), ['slug' => str_repeat('a', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_update_rejects_non_string_name(): void
    {
        $tag = NutrientTag::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('nutrient-tags.update', $tag), ['name' => ['not', 'a', 'string']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_rejects_non_string_description(): void
    {
        $tag = NutrientTag::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('nutrient-tags.update', $tag), ['description' => 12345])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    public function test_delete_removes_nutrient_tag(): void
    {
        $tag = NutrientTag::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('nutrient-tags.delete', $tag))
            ->assertStatus(204);

        $this->assertDatabaseMissing('nutrient_tags', ['id' => $tag->id]);
    }

    public function test_delete_with_attached_nutrients_removes_pivot_rows(): void
    {
        $tag = NutrientTag::factory()->create();
        $nutrients = Nutrient::factory()->count(2)->create();
        $tag->nutrients()->attach($nutrients->pluck('id'));

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('nutrient-tags.delete', $tag))
            ->assertStatus(204);

        $this->assertDatabaseMissing('nutrient_tags', ['id' => $tag->id]);
        $this->assertDatabaseCount('nutrient_nutrient_tag', 0);
    }

    // -------------------------------------------------------------------------
    // attach
    // -------------------------------------------------------------------------

    public function test_attach_adds_tag_to_nutrient(): void
    {
        $tag = NutrientTag::factory()->create();

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('nutrients.tags.attach', $this->nutrient), ['tag_id' => $tag->id]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('nutrient_nutrient_tag', [
            'nutrient_id'     => $this->nutrient->id,
            'nutrient_tag_id' => $tag->id,
        ]);
        $this->assertCount(1, $response->json());
    }

    public function test_attach_is_idempotent(): void
    {
        $tag = NutrientTag::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('nutrients.tags.attach', $this->nutrient), ['tag_id' => $tag->id])
            ->assertStatus(200);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('nutrients.tags.attach', $this->nutrient), ['tag_id' => $tag->id])
            ->assertStatus(200);

        $this->assertDatabaseCount('nutrient_nutrient_tag', 1);
    }

    public function test_attach_requires_tag_id(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('nutrients.tags.attach', $this->nutrient), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tag_id']);
    }

    public function test_attach_rejects_nonexistent_tag_id(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('nutrients.tags.attach', $this->nutrient), ['tag_id' => 99999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tag_id']);
    }

    // -------------------------------------------------------------------------
    // detach
    // -------------------------------------------------------------------------

    public function test_detach_removes_single_tag(): void
    {
        $tags = NutrientTag::factory()->count(2)->create();
        $this->nutrient->tags()->attach($tags->pluck('id'));

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('nutrients.tags.detach', [$this->nutrient, $tags->first()]))
            ->assertStatus(204);

        $this->assertDatabaseMissing('nutrient_nutrient_tag', [
            'nutrient_id'     => $this->nutrient->id,
            'nutrient_tag_id' => $tags->first()->id,
        ]);
        $this->assertDatabaseHas('nutrient_nutrient_tag', [
            'nutrient_id'     => $this->nutrient->id,
            'nutrient_tag_id' => $tags->last()->id,
        ]);
    }

    public function test_detach_nonattached_tag_is_idempotent(): void
    {
        $tag = NutrientTag::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('nutrients.tags.detach', [$this->nutrient, $tag]))
            ->assertStatus(204);
    }

    // -------------------------------------------------------------------------
    // detachAll
    // -------------------------------------------------------------------------

    public function test_detach_all_removes_all_tags(): void
    {
        $tags = NutrientTag::factory()->count(3)->create();
        $this->nutrient->tags()->attach($tags->pluck('id'));

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('nutrients.tags.detach-all', $this->nutrient))
            ->assertStatus(204);

        $this->assertDatabaseCount('nutrient_nutrient_tag', 0);
    }

    public function test_detach_all_on_nutrient_with_no_tags_is_idempotent(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('nutrients.tags.detach-all', $this->nutrient))
            ->assertStatus(204);
    }

    protected function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }
}