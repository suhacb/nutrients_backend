<?php

namespace Tests\Feature\Nutrients;

use Tests\TestCase;
use App\Models\Unit;
use App\Models\Nutrient;
use Tests\LoginTestUser;
use App\Models\Ingredient;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NutrientsControllerTest extends TestCase
{
    use RefreshDatabase, LoginTestUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->login();
    }

    public function test_index_returns_nutrients(): void
    {
        $count = 90;
        $perPage = 25;
        $page = 4;
        Nutrient::factory()->count($count)->create();
        
        // Get first page
        $response = $this->withHeaders($this->makeAuthRequestHeader())->getJson(route('nutrients.index'));
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
        $response = $this->withHeaders($this->makeAuthRequestHeader())->getJson(route('nutrients.index') . '?page=' . $page);
        $json = $response->json();
        $expectedItems = min($perPage, $count - $perPage * ($page - 1));
        $this->assertCount($expectedItems, $json['data']);
        $this->assertEquals($page, $json['current_page']);
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
            'external_id' => '101',
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
        $expectedNutrient = $nutrient;
        $expectedNutrient->name = 'New Name';
        
        $response->assertStatus(200)->assertJsonFragment(['name' => 'New Name']);;
        $this->assertDatabaseHas('nutrients', ['name' => 'New Name']);
    }

    public function test_deletes_nutrient(): void
    {
        $nutrient = Nutrient::factory()->create();
        $response = $this->withHeaders($this->makeAuthRequestHeader())->deleteJson(route('nutrients.delete', $nutrient));

        $response->assertStatus(204);

        $this->assertSoftDeleted('nutrients', ['id' => $nutrient->id]);
    }

    public function test_cannot_delete_nutrient_if_attached_to_ingredient(): void
    {
        // Create a nutrient
        $nutrient = Nutrient::factory()->create();

        // Create a unit and ingredient
        $unit = Unit::firstOrCreate(
            ['name' => 'milligram', 'type' => 'mass'],
            ['abbreviation' => 'mg']
        );
        $ingredient = Ingredient::factory()->create();

        // Attach nutrient to ingredient
        $ingredient->nutrients()->attach($nutrient->id, [
            'amount' => 10,
            'amount_unit_id' => $unit->id,
        ]);

        // Attempt to delete the nutrient via the controller
        $response = $this->withHeaders($this->makeAuthRequestHeader())
                        ->deleteJson(route('nutrients.delete', $nutrient));

        $response->assertStatus(409)
                ->assertJson([
                    'message' => 'Cannot delete nutrient: it is attached to one or more ingredients.'
                ]);

        // Assert the nutrient still exists
        $this->assertDatabaseHas('nutrients', ['id' => $nutrient->id]);
    }

    public function test_store_requires_name_and_source(): void
    {
        $response = $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('nutrients.store'), []);

        $response->assertStatus(422)->assertJsonValidationErrors([
            'source',
            'name',
        ]);
    }

    protected function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }
}
