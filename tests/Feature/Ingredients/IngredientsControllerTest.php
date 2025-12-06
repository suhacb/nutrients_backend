<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Unit;
use App\Models\Nutrient;
use Tests\LoginTestUser;
use App\Models\Ingredient;
use App\Jobs\SyncIngredientToSearch;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IngredientsControllerTest extends TestCase
{
    use RefreshDatabase, LoginTestUser;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // Prevent actual jobs from running
        $this->login();
    }

    public function test_index_returns_paginated_ingredients(): void
    {
        $count = 90;
        $perPage = 25;
        $page = 4;

        $unit = Unit::factory()->create();

        Ingredient::factory()->count(90)->create(['default_amount_unit_id' => $unit->id]);
        
        // Get first page
        $response = $this->withHeaders($this->makeAuthRequestHeader())->getJson(route('ingredients.index'));
        $response->assertStatus(200);

        $json = $response->json();

        // Assert pagination meta exists
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('current_page', $json);
        $this->assertArrayHasKey('last_page', $json);
        $this->assertArrayHasKey('per_page', $json);
        $this->assertArrayHasKey('total', $json);

        // Assert 25 items returned on the first page
        $this->assertCount($perPage, $json['data']);

        // Assert total is correct
        $this->assertEquals($count, $json['total']);

        // Assert number of items for provided page
        $response = $this->withHeaders($this->makeAuthRequestHeader())->getJson(route('ingredients.index') . '?page=' . $page);
        $json = $response->json();
        $expectedItems = min($perPage, $count - $perPage * ($page - 1));
        $this->assertCount($expectedItems, $json['data']);
        $this->assertEquals($page, $json['current_page']);
    }

    public function test_show_returns_single_ingredient_with_relationships(): void
    {
        $defaultUnit = Unit::factory()->create(['name' => 'gram', 'abbreviation' => 'g', 'type' => 'mass']);
        $amountUnit = Unit::factory()->create(['name' => 'milligram', 'abbreviation' => 'mg', 'type' => 'mass']);

        $ingredient = Ingredient::factory()->create([
            'default_amount_unit_id' => $defaultUnit->id,
            'name' => 'Test Ingredient',
        ]);

        $nutrient = Nutrient::factory()->create(['name' => 'Protein']);

        $ingredient->nutrients()->attach($nutrient->id, [
            'amount' => 5,
            'amount_unit_id' => $amountUnit->id,
            'portion_amount' => 2,
            'portion_amount_unit_id' => $amountUnit->id,
        ]);

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('ingredients.show', $ingredient));

        $response->assertStatus(200)
            ->assertJson([
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'default_amount_unit' => [
                    'id' => $defaultUnit->id,
                    'name' => $defaultUnit->name,
                ],
                'nutrients' => [
                    [
                        'id' => $nutrient->id,
                        'name' => $nutrient->name,
                        'pivot' => [
                            'amount' => 5,
                            'amount_unit_id' => $amountUnit->id,
                            'portion_amount' => 2,
                            'portion_amount_unit_id' => $amountUnit->id,
                        ],
                    ]
                ]
            ]);

        $json = $response->json();

        // Additional assertions to ensure relationships are loaded
        $this->assertArrayHasKey('default_amount_unit', $json);
        $this->assertArrayHasKey('nutrients', $json);
        $this->assertCount(1, $json['nutrients']);
        $this->assertArrayHasKey('pivot', $json['nutrients'][0]);
    }

    public function test_store_creates_ingredient_and_dispatches_job(): void
    {
        $unit = Unit::factory()->create();

        $ingredient = Ingredient::factory()->make([
            'name' => 'Test Ingredient',
            'description' => 'A test ingredient',
            'default_amount_unit_id' => $unit->id
        ]);
 
        $payload = $ingredient->toArray();

        $response = $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('ingredients.store'), $payload);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'Test Ingredient',
            ]);

        $ingredient = Ingredient::first();
        $this->assertNotNull($ingredient);

        // Assert job was dispatched
        Queue::assertPushed(SyncIngredientToSearch::class, function ($job) use ($ingredient) {
            return $job->ingredient->id === $ingredient->id && $job->action === 'insert';
        });
    }

    public function test_update_modifies_ingredient_and_dispatches_job(): void
    {
        $unit = Unit::factory()->create();

        $ingredient = Ingredient::factory()->create([
            'name' => 'Old Ingredient',
            'description' => 'A test ingredient',
            'default_amount_unit_id' => $unit->id
        ]);

        $payload = [
            'name' => 'New Ingredient',
        ];

        $response = $this->withHeaders($this->makeAuthRequestHeader())->putJson(route('ingredients.update', $ingredient), $payload);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'New Ingredient',
            ]);

        $ingredient->refresh();
        $this->assertEquals('New Ingredient', $ingredient->name);

        Queue::assertPushed(SyncIngredientToSearch::class, function ($job) use ($ingredient) {
            return $job->ingredient->id === $ingredient->id && $job->action === 'update';
        });
    }

    public function test_delete_soft_deletes_ingredient_and_dispatches_job(): void
    {
        $unit = Unit::factory()->create();

        $ingredient = Ingredient::factory()->create([
            'name' => 'Old Ingredient',
            'description' => 'A test ingredient',
            'default_amount_unit_id' => $unit->id
        ]);

        $response = $this->withHeaders($this->makeAuthRequestHeader())->deleteJson(route('ingredients.delete', $ingredient));

        $response->assertStatus(204);

        $this->assertSoftDeleted('ingredients', [
            'id' => $ingredient->id,
        ]);

        Queue::assertPushed(SyncIngredientToSearch::class, function ($job) use ($ingredient) {
            $jobId = $job->ingredient?->id ?? $job->id; // safely get id
            return $jobId === $ingredient->id && $job->action === 'delete';
        });
    }

    // public function test_search_returns_results(): void
    // {
    //     // We can just mock the search job / service if needed
    //     $ingredient = Ingredient::factory()->create(['name' => 'Sugar']);
    // 
    //     // Here, we simulate a search request
    //     $response = $this->withHeaders($this->makeAuthRequestHeader())->getJson('/api/ingredients/search?q=Sugar');
    // 
    //     $response->assertStatus(200);
    //     $response->assertJsonFragment([
    //         'name' => 'Sugar',
    //     ]);
    // }

    public function test_store_validation_fails_with_missing_required_fields(): void
    {
        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('ingredients.store'), []); // empty payload

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'source',
            'name',
            'default_amount',
            'default_amount_unit_id',
        ]);

        $errors = $response->json('errors');

        $this->assertEquals(
            'Source is required.',
            $errors['source'][0]
        );
    }

    public function test_update_validation_fails_with_invalid_default_amount(): void
    {
        $unit = Unit::factory()->create();

        $ingredient = Ingredient::factory()->create([
            'name' => 'Carrot',
            'default_amount_unit_id' => $unit->id,
        ]);

        $payload = [
            'default_amount' => -50, // invalid
        ];

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('ingredients.update', $ingredient), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['default_amount']);
        $this->assertEquals(
            'Default amount must be at least 0.',
            $response->json('errors.default_amount.0')
        );
    }

    public function test_store_validation_fails_on_duplicate_external_id_source_name(): void
    {
        $unit = Unit::factory()->create();

        Ingredient::factory()->create([
            'external_id' => '123',
            'source' => 'USDA',
            'name' => 'Carrot',
            'default_amount_unit_id' => $unit->id,
        ]);

        $payload = [
            'external_id' => '123',
            'source' => 'USDA',
            'name' => 'Carrot',
            'default_amount' => 100,
            'default_amount_unit_id' => $unit->id,
        ];

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('ingredients.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['external_id']);
        $this->assertEquals(
            'The combination of external ID, source, and name must be unique.',
            $response->json('errors.external_id.0')
        );
    }

    protected function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }

}
