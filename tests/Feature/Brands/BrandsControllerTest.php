<?php

namespace Tests\Feature\Brands;

use App\Jobs\SyncIngredientToSearch;
use App\Models\Brand;
use App\Models\Ingredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\LoginTestUser;
use Tests\TestCase;

class BrandsControllerTest extends TestCase
{
    use RefreshDatabase, LoginTestUser;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->login();
    }

    public function test_index_returns_paginated_brands(): void
    {
        Brand::factory()->count(30)->create();

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('brands.index'));

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
            ->getJson(route('brands.index') . '?page=2')
            ->json();

        $this->assertCount(5, $page2['data']);
        $this->assertEquals(2, $page2['current_page']);
    }

    public function test_show_returns_single_brand_with_ingredients_count(): void
    {
        $brand      = Brand::factory()->create();
        $ingredient = Ingredient::factory()->create(['brand_id' => $brand->id]);

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('brands.show', $brand))
            ->assertStatus(200);

        $json = $response->json();

        $this->assertEquals($brand->id, $json['id']);
        $this->assertEquals($brand->name, $json['name']);
        $this->assertEquals($brand->owner, $json['owner']);
        $this->assertEquals($brand->slug, $json['slug']);
        $this->assertArrayHasKey('ingredients_count', $json);
        $this->assertEquals(1, $json['ingredients_count']);
    }

    public function test_store_creates_brand(): void
    {
        $payload = [
            'name'        => 'KROGER',
            'owner'       => 'The Kroger Co.',
            'slug'        => 'kroger',
            'country'     => 'United States',
            'description' => 'An American retail company.',
        ];

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('brands.store'), $payload)
            ->assertStatus(201)
            ->assertJson($payload);

        $this->assertDatabaseHas('brands', ['slug' => 'kroger']);
    }

    public function test_store_requires_name(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('brands.store'), ['owner' => 'ACME Corp', 'slug' => 'acme'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_requires_owner(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('brands.store'), ['name' => 'ACME', 'slug' => 'acme'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['owner']);
    }

    public function test_store_requires_slug(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('brands.store'), ['name' => 'ACME', 'owner' => 'ACME Corp'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_store_rejects_duplicate_slug(): void
    {
        Brand::factory()->create(['slug' => 'acme']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('brands.store'), ['name' => 'ACME', 'owner' => 'ACME Corp', 'slug' => 'acme'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_store_rejects_slug_exceeding_max_length(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('brands.store'), [
                'name'  => 'ACME',
                'owner' => 'ACME Corp',
                'slug'  => str_repeat('a', 256),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_store_accepts_optional_fields_omitted(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('brands.store'), ['name' => 'ACME', 'owner' => 'ACME Corp', 'slug' => 'acme'])
            ->assertStatus(201);

        $row = \Illuminate\Support\Facades\DB::table('brands')->where('slug', 'acme')->first();
        $this->assertNull($row->country);
        $this->assertNull($row->description);
    }

    public function test_update_modifies_brand(): void
    {
        $brand = Brand::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('brands.update', $brand), [
                'name'  => 'Updated Name',
                'owner' => 'Updated Owner',
                'slug'  => 'updated-slug',
            ])
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Name', 'owner' => 'Updated Owner', 'slug' => 'updated-slug']);

        $this->assertDatabaseHas('brands', ['id' => $brand->id, 'slug' => 'updated-slug']);
    }

    public function test_update_allows_same_slug_on_same_record(): void
    {
        $brand = Brand::factory()->create(['slug' => 'my-brand']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('brands.update', $brand), ['name' => 'Updated Name', 'slug' => 'my-brand'])
            ->assertStatus(200);
    }

    public function test_update_rejects_duplicate_slug(): void
    {
        Brand::factory()->create(['slug' => 'taken-slug']);
        $brand = Brand::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('brands.update', $brand), ['slug' => 'taken-slug'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_update_accepts_empty_payload(): void
    {
        $brand = Brand::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('brands.update', $brand), [])
            ->assertStatus(200);
    }

    public function test_update_dispatches_sync_for_related_ingredients(): void
    {
        $brand       = Brand::factory()->create();
        $ingredient1 = Ingredient::factory()->create(['brand_id' => $brand->id]);
        $ingredient2 = Ingredient::factory()->create(['brand_id' => $brand->id]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('brands.update', $brand), ['name' => 'Updated Name'])
            ->assertStatus(200);

        Queue::assertPushed(SyncIngredientToSearch::class, fn($job) => $job->id === $ingredient1->id);
        Queue::assertPushed(SyncIngredientToSearch::class, fn($job) => $job->id === $ingredient2->id);
    }

    public function test_delete_soft_deletes_brand(): void
    {
        $brand = Brand::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('brands.delete', $brand))
            ->assertStatus(204);

        $this->assertSoftDeleted('brands', ['id' => $brand->id]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('brands.index'))
            ->assertJsonMissing(['id' => $brand->id]);
    }

    protected function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }
}
