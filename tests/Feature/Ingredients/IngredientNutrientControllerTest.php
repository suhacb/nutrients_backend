<?php

namespace Tests\Feature\Ingredients;

use App\Models\Ingredient;
use App\Models\Nutrient;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\LoginTestUser;
use Tests\MakesUnit;
use Tests\TestCase;

class IngredientNutrientControllerTest extends TestCase
{
    use RefreshDatabase, LoginTestUser, MakesUnit;

    protected Ingredient $ingredient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->login();
        Queue::fake();
        $this->ingredient = Ingredient::factory()->create();
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_returns_attached_nutrients(): void
    {
        $unit = $this->makeUnit();
        $nutrients = Nutrient::factory()->count(3)->create();
        foreach ($nutrients as $nutrient) {
            $this->ingredient->nutrients()->attach($nutrient->id, ['amount' => 10.0, 'amount_unit_id' => $unit->id]);
        }

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('ingredients.nutrients.index', $this->ingredient));

        $response->assertStatus(200);
        $this->assertCount(3, $response->json());
        $pivot = $response->json()[0]['pivot'];
        $this->assertArrayHasKey('amount', $pivot);
        $this->assertArrayHasKey('amount_unit_id', $pivot);
        $this->assertEquals(10.0, $pivot['amount']);
        $this->assertEquals($unit->id, $pivot['amount_unit_id']);
    }

    public function test_index_returns_empty_array_when_no_nutrients_attached(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('ingredients.nutrients.index', $this->ingredient))
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    // -------------------------------------------------------------------------
    // attach
    // -------------------------------------------------------------------------

    public function test_attach_adds_nutrient_to_ingredient(): void
    {
        $nutrient = Nutrient::factory()->create();
        $unit = $this->makeUnit();

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('ingredients.nutrients.attach', $this->ingredient), [
                'nutrient_id' => $nutrient->id,
                'amount' => 42.5,
                'amount_unit_id' => $unit->id
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('ingredient_nutrient', [
            'ingredient_id' => $this->ingredient->id,
            'nutrient_id'   => $nutrient->id,
        ]);
        $pivot = $response->json()[0]['pivot'];
        $this->assertEquals(42.5, $pivot['amount']);
        $this->assertEquals($unit->id, $pivot['amount_unit_id']);
    }

    public function test_attach_stores_pivot_amount_and_unit(): void
    {
        $nutrient = Nutrient::factory()->create();
        $unit = $this->makeUnit();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('ingredients.nutrients.attach', $this->ingredient), [
                'nutrient_id'    => $nutrient->id,
                'amount'         => 42.5,
                'amount_unit_id' => $unit->id,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('ingredient_nutrient', [
            'ingredient_id'  => $this->ingredient->id,
            'nutrient_id'    => $nutrient->id,
            'amount'         => 42.5,
            'amount_unit_id' => $unit->id,
        ]);
    }

    public function test_attach_is_idempotent(): void
    {
        $nutrient = Nutrient::factory()->create();
        $unit = $this->makeUnit();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('ingredients.nutrients.attach', $this->ingredient), [
                'nutrient_id' => $nutrient->id,
                'amount' => 42.5,
                'amount_unit_id' => $unit->id,
                ]
            )
            ->assertStatus(200);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('ingredients.nutrients.attach', $this->ingredient), [
                'nutrient_id' => $nutrient->id,
                'amount' => 42.5,
                'amount_unit_id' => $unit->id,
                ]
            )
            ->assertStatus(200);

        $this->assertDatabaseCount('ingredient_nutrient', 1);
    }

    public function test_attach_requires_nutrient_id(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('ingredients.nutrients.attach', $this->ingredient), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nutrient_id']);
    }

    public function test_attach_rejects_nonexistent_nutrient_id(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('ingredients.nutrients.attach', $this->ingredient), ['nutrient_id' => 99999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nutrient_id']);
    }

    public function test_attach_rejects_negative_amount(): void
    {
        $nutrient = Nutrient::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('ingredients.nutrients.attach', $this->ingredient), [
                'nutrient_id' => $nutrient->id,
                'amount'      => -1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_attach_rejects_nonexistent_unit(): void
    {
        $nutrient = Nutrient::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('ingredients.nutrients.attach', $this->ingredient), [
                'nutrient_id'    => $nutrient->id,
                'amount_unit_id' => 99999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount_unit_id']);
    }

    // -------------------------------------------------------------------------
    // updatePivot
    // -------------------------------------------------------------------------

    public function test_update_pivot_modifies_amount_and_unit(): void
    {
        $unit = $this->makeUnit();
        $nutrient = Nutrient::factory()->create();
        $this->ingredient->nutrients()->attach($nutrient->id, ['amount' => 5.0, 'amount_unit_id' => $unit->id]);

        $newUnit = Unit::factory()->create();

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('ingredients.nutrients.update-pivot', [$this->ingredient, $nutrient]), [
                'amount'         => 99.9,
                'amount_unit_id' => $newUnit->id,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('ingredient_nutrient', [
            'ingredient_id'  => $this->ingredient->id,
            'nutrient_id'    => $nutrient->id,
            'amount'         => 99.9,
            'amount_unit_id' => $newUnit->id,
        ]);
        $pivot = $response->json()[0]['pivot'];
        $this->assertEquals(99.9, $pivot['amount']);
        $this->assertEquals($newUnit->id, $pivot['amount_unit_id']);
    }

    public function test_update_pivot_accepts_partial_payload(): void
    {
        $unit = $this->makeUnit();
        $nutrient = Nutrient::factory()->create();
        $this->ingredient->nutrients()->attach($nutrient->id, ['amount' => 5.0, 'amount_unit_id' => $unit->id]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('ingredients.nutrients.update-pivot', [$this->ingredient, $nutrient]), [
                'amount' => 20.0,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('ingredient_nutrient', [
            'ingredient_id'  => $this->ingredient->id,
            'nutrient_id'    => $nutrient->id,
            'amount'         => 20.0,
            'amount_unit_id' => $unit->id,
        ]);
    }

    public function test_update_pivot_rejects_negative_amount(): void
    {
        $unit = $this->makeUnit();
        $nutrient = Nutrient::factory()->create();
        $this->ingredient->nutrients()->attach($nutrient->id, ['amount' => 5.0, 'amount_unit_id' => $unit->id]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('ingredients.nutrients.update-pivot', [$this->ingredient, $nutrient]), [
                'amount' => -5,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_update_pivot_rejects_nonexistent_unit(): void
    {
        $unit = $this->makeUnit();
        $nutrient = Nutrient::factory()->create();
        $this->ingredient->nutrients()->attach($nutrient->id, ['amount' => 5.0, 'amount_unit_id' => $unit->id]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('ingredients.nutrients.update-pivot', [$this->ingredient, $nutrient]), [
                'amount_unit_id' => 99999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount_unit_id']);
    }

    public function test_update_pivot_on_nonattached_nutrient_is_silent_no_op(): void
    {
        $nutrient = Nutrient::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('ingredients.nutrients.update-pivot', [$this->ingredient, $nutrient]), [
                'amount' => 10.0,
            ])
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    // -------------------------------------------------------------------------
    // detach (single)
    // -------------------------------------------------------------------------

    public function test_detach_removes_single_nutrient(): void
    {
        $unit = $this->makeUnit();
        $nutrients = Nutrient::factory()->count(2)->create();
        $this->ingredient->nutrients()->attach($nutrients->pluck('id'), ['amount' => 1.0, 'amount_unit_id' => $unit->id]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('ingredients.nutrients.detach', [$this->ingredient, $nutrients->first()]))
            ->assertStatus(204);

        $this->assertDatabaseMissing('ingredient_nutrient', [
            'ingredient_id' => $this->ingredient->id,
            'nutrient_id'   => $nutrients->first()->id,
        ]);
        $this->assertDatabaseHas('ingredient_nutrient', [
            'ingredient_id' => $this->ingredient->id,
            'nutrient_id'   => $nutrients->last()->id,
        ]);
    }

    public function test_detach_nonattached_nutrient_is_idempotent(): void
    {
        $nutrient = Nutrient::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('ingredients.nutrients.detach', [$this->ingredient, $nutrient]))
            ->assertStatus(204);
    }

    // -------------------------------------------------------------------------
    // detachAll (handles both selective and full detach)
    // -------------------------------------------------------------------------

    public function test_detach_all_removes_all_nutrients(): void
    {
        $unit = $this->makeUnit();
        $nutrients = Nutrient::factory()->count(3)->create();
        $this->ingredient->nutrients()->attach($nutrients->pluck('id'), ['amount' => 1.0, 'amount_unit_id' => $unit->id]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('ingredients.nutrients.detach-all', $this->ingredient))
            ->assertStatus(204);

        $this->assertDatabaseCount('ingredient_nutrient', 0);
    }

    public function test_detach_all_with_nutrient_ids_removes_only_specified(): void
    {
        $unit = $this->makeUnit();
        $nutrients = Nutrient::factory()->count(3)->create();
        $this->ingredient->nutrients()->attach($nutrients->pluck('id'), ['amount' => 1.0, 'amount_unit_id' => $unit->id]);

        $toDetach = $nutrients->take(2)->pluck('id')->all();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('ingredients.nutrients.detach-all', $this->ingredient), [
                'nutrient_ids' => $toDetach,
            ])
            ->assertStatus(204);

        $this->assertDatabaseCount('ingredient_nutrient', 1);
        $this->assertDatabaseHas('ingredient_nutrient', [
            'ingredient_id' => $this->ingredient->id,
            'nutrient_id'   => $nutrients->last()->id,
        ]);
    }

    public function test_detach_all_on_ingredient_with_no_nutrients_is_idempotent(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('ingredients.nutrients.detach-all', $this->ingredient))
            ->assertStatus(204);
    }

    public function test_detach_all_rejects_empty_nutrient_ids_array(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('ingredients.nutrients.detach-all', $this->ingredient), [
                'nutrient_ids' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nutrient_ids']);
    }

    public function test_detach_all_rejects_nonexistent_nutrient_ids(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('ingredients.nutrients.detach-all', $this->ingredient), [
                'nutrient_ids' => [99999],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nutrient_ids.0']);
    }

    protected function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }
}