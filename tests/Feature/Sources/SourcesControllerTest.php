<?php

namespace Tests\Feature\Sources;

use App\Models\Nutrient;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\LoginTestUser;
use Tests\TestCase;

class SourcesControllerTest extends TestCase
{
    use RefreshDatabase, LoginTestUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->login();
    }

    /**
     * Verifies that the index endpoint returns paginated results with the correct
     * pagination structure, 25 items on the first page, and the remainder on page 2.
     */
    public function test_index_returns_paginated_sources(): void
    {
        foreach (range(1, 30) as $i) {
            Source::create(['name' => "Source {$i}", 'slug' => "source-{$i}"]);
        }

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('sources.index'));

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
            ->getJson(route('sources.index') . '?page=2')
            ->json();

        $this->assertCount(5, $page2['data']);
        $this->assertEquals(2, $page2['current_page']);
    }

    /**
     * Verifies that the show endpoint returns all stored fields for a single source.
     */
    public function test_show_returns_single_source(): void
    {
        $source = Source::create([
            'name'        => 'USDA FoodData Central',
            'slug'        => 'usda',
            'url'         => 'https://fdc.nal.usda.gov',
            'description' => 'The USDA national nutrient database.',
        ]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('sources.show', $source))
            ->assertStatus(200)
            ->assertJson([
                'id'          => $source->id,
                'name'        => $source->name,
                'slug'        => $source->slug,
                'url'         => $source->url,
                'description' => $source->description,
            ]);
    }

    /**
     * Verifies that a valid POST request creates a new source and persists it to the database.
     */
    public function test_store_creates_source(): void
    {
        $payload = [
            'name'        => 'USDA FoodData Central',
            'slug'        => 'usda',
            'url'         => 'https://fdc.nal.usda.gov',
            'description' => 'The USDA national nutrient database.',
        ];

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('sources.store'), $payload)
            ->assertStatus(201)
            ->assertJson($payload);

        $this->assertDatabaseHas('sources', ['slug' => 'usda']);
    }

    /**
     * Verifies that submitting an empty payload returns a 422 with validation errors
     * for the required name and slug fields.
     */
    public function test_store_requires_name_and_slug(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('sources.store'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'slug']);
    }

    /**
     * Verifies that a slug already taken by another source causes a 422 validation error.
     */
    public function test_store_rejects_duplicate_slug(): void
    {
        Source::create(['name' => 'USDA FoodData Central', 'slug' => 'usda']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('sources.store'), ['name' => 'USDA Duplicate', 'slug' => 'usda'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    /**
     * Verifies that url and description are optional — omitting them succeeds and
     * the columns are stored as NULL.
     */
    public function test_store_accepts_optional_fields_omitted(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('sources.store'), ['name' => 'EFSA', 'slug' => 'efsa'])
            ->assertStatus(201);

        $row = \Illuminate\Support\Facades\DB::table('sources')->where('slug', 'efsa')->first();
        $this->assertNull($row->url);
        $this->assertNull($row->description);
    }

    /**
     * Verifies that a PUT request updates the source fields and reflects them in the response
     * and in the database.
     */
    public function test_update_modifies_source(): void
    {
        $source = Source::create(['name' => 'Old Name', 'slug' => 'old-slug']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('sources.update', $source), ['name' => 'New Name', 'slug' => 'new-slug'])
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'New Name', 'slug' => 'new-slug']);

        $this->assertDatabaseHas('sources', ['id' => $source->id, 'name' => 'New Name', 'slug' => 'new-slug']);
    }

    /**
     * Verifies that updating a source with a slug already used by a different source
     * returns a 422 validation error.
     */
    public function test_update_rejects_duplicate_slug(): void
    {
        Source::create(['name' => 'USDA FoodData Central', 'slug' => 'usda']);
        $source = Source::create(['name' => 'EFSA', 'slug' => 'efsa']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('sources.update', $source), ['slug' => 'usda'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    /**

     * Verifies that a source can be updated while keeping its own slug (unique rule
     * must exclude the record being updated).
     */
    public function test_update_allows_same_slug_on_same_record(): void
    {
        $source = Source::create(['name' => 'USDA FoodData Central', 'slug' => 'usda']);
        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('sources.update', $source), ['name' => 'USDA Updated', 'slug' => 'usda'])
            ->assertStatus(200);
    }

    /**
     * Verifies that a name exceeding 255 characters returns a 422 validation error.
     */
    public function test_store_rejects_name_exceeding_max_length(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('sources.store'), [
                'name' => str_repeat('a', 256),
                'slug' => 'valid-slug',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * Verifies that a slug exceeding 255 characters returns a 422 validation error.
     */
    public function test_store_rejects_slug_exceeding_max_length(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('sources.store'), [
                'name' => 'Valid Name',
                'slug' => str_repeat('a', 256),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    /**
     * Verifies that a non-string name returns a 422 validation error.
     */
    public function test_store_rejects_non_string_name(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('sources.store'), [
                'name' => ['not', 'a', 'string'],
                'slug' => 'valid-slug',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * Verifies that a non-string slug returns a 422 validation error.
     */
    public function test_store_rejects_non_string_slug(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('sources.store'), [
                'name' => 'Valid Name',
                'slug' => ['not', 'a', 'string'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    /**
     * Verifies that providing an invalid URL format for the url field returns a 422 validation error.
     */
    public function test_store_rejects_invalid_url(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('sources.store'), [
                'name' => 'Valid Name',
                'slug' => 'valid-slug',
                'url'  => 'not-a-valid-url',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    /**
     * Verifies that a well-formed URL is accepted without validation errors.
     */
    public function test_store_accepts_valid_url(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('sources.store'), [
                'name' => 'Valid Name',
                'slug' => 'valid-slug',
                'url'  => 'https://example.com/data',
            ])
            ->assertStatus(201);
    }

    /**
     * Verifies that a non-string description returns a 422 validation error.
     */
    public function test_store_rejects_non_string_description(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('sources.store'), [
                'name'        => 'Valid Name',
                'slug'        => 'valid-slug',
                'description' => ['not', 'a', 'string'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    /**
     * Verifies that a PUT request with no fields succeeds — all fields are optional on update.
     */
    public function test_update_accepts_empty_payload(): void
    {
        $source = Source::create(['name' => 'Old Name', 'slug' => 'old-slug']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('sources.update', $source), [])
            ->assertStatus(200);
    }

    /**
     * Verifies that a name exceeding 255 characters on update returns a 422 validation error.
     */
    public function test_update_rejects_name_exceeding_max_length(): void
    {
        $source = Source::create(['name' => 'Old Name', 'slug' => 'old-slug']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('sources.update', $source), ['name' => str_repeat('a', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * Verifies that a slug exceeding 255 characters on update returns a 422 validation error.
     */
    public function test_update_rejects_slug_exceeding_max_length(): void
    {
        $source = Source::create(['name' => 'Old Name', 'slug' => 'old-slug']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('sources.update', $source), ['slug' => str_repeat('a', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    /**
     * Verifies that providing an invalid URL format on update returns a 422 validation error.
     */
    public function test_update_rejects_invalid_url(): void
    {
        $source = Source::create(['name' => 'Old Name', 'slug' => 'old-slug']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('sources.update', $source), ['url' => 'not-a-valid-url'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    /**
     * Verifies that a well-formed URL is accepted on update without validation errors.
     */
    public function test_update_accepts_valid_url(): void
    {
        $source = Source::create(['name' => 'Old Name', 'slug' => 'old-slug']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('sources.update', $source), ['url' => 'https://updated-source.org'])
            ->assertStatus(200)
            ->assertJsonFragment(['url' => 'https://updated-source.org']);
    }

    /**
     * Verifies that a non-string name on update returns a 422 validation error.
     */
    public function test_update_rejects_non_string_name(): void
    {
        $source = Source::create(['name' => 'Old Name', 'slug' => 'old-slug']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('sources.update', $source), ['name' => ['not', 'a', 'string']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * Verifies that a non-string description on update returns a 422 validation error.
     */
    public function test_update_rejects_non_string_description(): void
    {
        $source = Source::create(['name' => 'Old Name', 'slug' => 'old-slug']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('sources.update', $source), ['description' => 12345])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    /**
     * Verifies that a DELETE request returns 204 and removes the source from the database.
     */
    public function test_delete_removes_source(): void
    {
        $source = Source::create(['name' => 'USDA FoodData Central', 'slug' => 'usda']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('sources.delete', $source))
            ->assertStatus(204);

        $this->assertDatabaseMissing('sources', ['id' => $source->id]);
    }

    /**
     * Verifies that deleting a source with attached nutrients returns 409 and
     * leaves the source record intact.
     */
    public function test_cannot_delete_source_with_nutrients(): void
    {
        Queue::fake();
        $source = Source::factory()->create();
        Nutrient::factory()->create(['source_id' => $source->id]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('sources.delete', $source))
            ->assertStatus(409)
            ->assertJson(['message' => 'Cannot delete source: it has one or more nutrients attached.']);

        $this->assertDatabaseHas('sources', ['id' => $source->id]);
    }

    protected function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }
}
