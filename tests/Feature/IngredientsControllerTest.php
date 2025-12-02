<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\LoginTestUser;
use App\Models\Ingredient;
use App\Jobs\SyncIngredientToSearch;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IngredientsControllerTest extends TestCase
{
    use RefreshDatabase, LoginTestUser;

    protected string $accessToken;
    protected string $refreshToken;
    protected string $appName;
    protected string $appUrl;
    protected string $authUrl;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // Prevent actual jobs from running

        $this->appName = config('nutrients.name');
        $this->appUrl = config('nutrients.frontend.url') . ':' . config('nutrients.frontend.port');
        $this->authUrl = config('nutrients.auth.url_backend') . ':' . config('nutrients.auth.port_backend');
        $token = $this->login();
        $this->accessToken = $token['access_token'] ?? null;
        $this->refreshToken = $token['refresh_token'] ?? null;
    }

    public function test_index_returns_paginated_ingredients(): void
    {
        Ingredient::factory()->count(15)->create();

        $response = $this->getJson(route('ingredients.index'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);

        $this->assertCount(15, Ingredient::all());
    }

    public function test_show_returns_single_ingredient(): void
    {
        $ingredient = Ingredient::factory()->create();

        $response = $this->getJson(route('ingredients.show', $ingredient));

        $response->assertStatus(200)
            ->assertJson([
                'id' => $ingredient->id,
                'name' => $ingredient->name,
            ]);
    }

    public function test_store_creates_ingredient_and_dispatches_job(): void
    {
        $payload = [
            'name' => 'Test Ingredient',
            'description' => 'A test ingredient',
        ];

        $response = $this->postJson(route('ingredients.store'), $payload);

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
        $ingredient = Ingredient::factory()->create([
            'name' => 'Old Name',
        ]);

        $payload = [
            'name' => 'Updated Name',
        ];

        $response = $this->putJson(route('ingredients.update', $ingredient), $payload);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Updated Name',
            ]);

        $ingredient->refresh();
        $this->assertEquals('Updated Name', $ingredient->name);

        Queue::assertPushed(SyncIngredientToSearch::class, function ($job) use ($ingredient) {
            return $job->ingredient->id === $ingredient->id && $job->action === 'update';
        });
    }

    public function test_delete_soft_deletes_ingredient_and_dispatches_job(): void
    {
        $ingredient = Ingredient::factory()->create();

        $response = $this->deleteJson(route('ingredients.delete', $ingredient));

        $response->assertStatus(204);

        $this->assertSoftDeleted('ingredients', [
            'id' => $ingredient->id,
        ]);

        Queue::assertPushed(SyncIngredientToSearch::class, function ($job) use ($ingredient) {
            return $job->ingredient->id === $ingredient->id && $job->action === 'delete';
        });
    }

    public function test_search_returns_results(): void
    {
        // We can just mock the search job / service if needed
        $ingredient = Ingredient::factory()->create(['name' => 'Sugar']);

        // Here, we simulate a search request
        $response = $this->getJson('/api/ingredients/search?q=Sugar');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => 'Sugar',
        ]);
    }

}
