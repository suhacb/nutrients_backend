<?php

namespace Tests\Feature\Nutrients;

use Tests\TestCase;
use App\Models\Nutrient;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\LoginTestUser;

class NutrientsControllerTest extends TestCase
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

        $this->appName = config('nutrients.name');
        $this->appUrl = config('nutrients.frontend.url') . ':' . config('nutrients.frontend.port');
        $this->authUrl = config('nutrients.auth.url_backend') . ':' . config('nutrients.auth.port_backend');
        $response = $this->login();
        $token = $this->login();
        $this->accessToken = $token['access_token'] ?? null;
        $this->refreshToken = $token['refresh_token'] ?? null;
    }

    public function test_index_returns_paginated_nutrients(): void
    {
        Nutrient::factory()->count(30)->create();
        
        
        $response = $this->withHeaders($this->makeAuthRequestHeader())->getJson(route('nutrients.index'));

        $response->assertStatus(200);
        $this->assertCount(30, $response->json());
    }

    public function test_show_returns_single_nutrient(): void
    {
        $nutrient = Nutrient::factory()->create();

        $response = $this->withHeaders($this->makeAuthRequestHeader())->getJson(route('nutrients.show', $nutrient));

        $response->assertStatus(200)
                 ->assertJson([
                     'id' => $nutrient->id,
                     'name' => $nutrient->name,
                 ]);
    }

    public function test_store_creates_nutrient(): void
    {
        $payload = [
            'source' => 'USDA FoodData Central',
            'external_id' => 101,
            'name' => 'Protein',
            'description' => 'Test nutrient',
            'derivation_code' => 'A',
            'derivation_description' => 'Derived test',
        ];

        $response = $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('nutrients.store'), $payload);

        $response->assertStatus(201)->assertJson($payload);

        $this->assertDatabaseHas('nutrients', ['name' => 'Protein']);
    }

    public function test_update_modifies_nutrient(): void
    {
        $nutrient = Nutrient::factory()->create(['name' => 'Old Name']);

        $payload = ['name' => 'New Name'];

        $response = $this->withHeaders($this->makeAuthRequestHeader())->putJson(route('nutrients.update', $nutrient), $payload);

        $response->assertStatus(200)->assertJson(['name' => 'New Name']);

        $this->assertDatabaseHas('nutrients', ['name' => 'New Name']);
    }

    public function test_deletes_nutrient(): void
    {
        $nutrient = Nutrient::factory()->create();

        $response = $this->withHeaders($this->makeAuthRequestHeader())->deleteJson(route('nutrients.delete', $nutrient));

        $response->assertStatus(200)->assertJson([]);

        $this->assertDatabaseMissing('nutrients', ['id' => $nutrient->id]);
    }

    public function test_store_requires_name_and_source(): void
    {
        $response = $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('nutrients.store'), []);

        $response->assertStatus(422)->assertJsonValidationErrors([
            'source',
            'external_id',
            'name',
            'description',
            'derivation_code',
            'derivation_description',
        ]);
    }
}
