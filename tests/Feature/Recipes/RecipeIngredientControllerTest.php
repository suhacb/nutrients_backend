<?php

namespace Tests\Feature\Recipes;

use App\Jobs\SyncRecipeToSearch;
use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\LoginTestUser;
use Tests\MakesUnit;
use Tests\TestCase;

class RecipeIngredientControllerTest extends TestCase
{
    use RefreshDatabase, LoginTestUser, MakesUnit;

    protected Recipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->login();
        Queue::fake();
        $this->recipe = Recipe::factory()->create();
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_returns_attached_ingredients(): void
    {
        $unit        = $this->makeUnit();
        $ingredients = Ingredient::factory()->count(3)->create(['default_amount_unit_id' => $unit->id]);

        foreach ($ingredients as $ingredient) {
            $this->recipe->ingredients()->attach($ingredient->id, [
                'amount'  => 100.0,
                'unit_id' => $unit->id,
            ]);
        }

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('recipes.ingredients.index', $this->recipe))
            ->assertStatus(200);

        $this->assertCount(3, $response->json());
        $pivot = $response->json()[0]['pivot'];
        $this->assertArrayHasKey('amount', $pivot);
        $this->assertArrayHasKey('unit_id', $pivot);
        $this->assertEquals(100.0, $pivot['amount']);
    }

    public function test_index_returns_empty_array_when_no_ingredients_attached(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('recipes.ingredients.index', $this->recipe))
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    // -------------------------------------------------------------------------
    // attach
    // -------------------------------------------------------------------------

    public function test_attach_adds_ingredient_to_recipe(): void
    {
        $unit       = $this->makeUnit();
        $ingredient = Ingredient::factory()->create(['default_amount_unit_id' => $unit->id]);

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.ingredients.attach', $this->recipe), [
                'ingredient_id' => $ingredient->id,
                'amount'        => 150.0,
                'unit_id'       => $unit->id,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('recipe_ingredient', [
            'recipe_id'     => $this->recipe->id,
            'ingredient_id' => $ingredient->id,
            'amount'        => 150.0,
            'unit_id'       => $unit->id,
        ]);
        $pivot = $response->json()[0]['pivot'];
        $this->assertEquals(150.0, $pivot['amount']);
    }

    public function test_attach_is_idempotent(): void
    {
        $unit       = $this->makeUnit();
        $ingredient = Ingredient::factory()->create(['default_amount_unit_id' => $unit->id]);
        $payload    = ['ingredient_id' => $ingredient->id, 'amount' => 100.0, 'unit_id' => $unit->id];

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.ingredients.attach', $this->recipe), $payload)
            ->assertStatus(200);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.ingredients.attach', $this->recipe), $payload)
            ->assertStatus(200);

        $this->assertDatabaseCount('recipe_ingredient', 1);
    }

    public function test_attach_requires_ingredient_id_amount_and_unit_id(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.ingredients.attach', $this->recipe), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ingredient_id', 'amount', 'unit_id']);
    }

    public function test_attach_rejects_nonexistent_ingredient(): void
    {
        $unit = $this->makeUnit();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.ingredients.attach', $this->recipe), [
                'ingredient_id' => 99999,
                'amount'        => 100.0,
                'unit_id'       => $unit->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ingredient_id']);
    }

    public function test_attach_rejects_zero_or_negative_amount(): void
    {
        $unit       = $this->makeUnit();
        $ingredient = Ingredient::factory()->create(['default_amount_unit_id' => $unit->id]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.ingredients.attach', $this->recipe), [
                'ingredient_id' => $ingredient->id,
                'amount'        => 0,
                'unit_id'       => $unit->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_attach_rejects_nonexistent_unit(): void
    {
        $ingredient = Ingredient::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.ingredients.attach', $this->recipe), [
                'ingredient_id' => $ingredient->id,
                'amount'        => 100.0,
                'unit_id'       => 99999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['unit_id']);
    }

    public function test_attach_dispatches_sync_job(): void
    {
        $unit       = $this->makeUnit();
        $ingredient = Ingredient::factory()->create(['default_amount_unit_id' => $unit->id]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.ingredients.attach', $this->recipe), [
                'ingredient_id' => $ingredient->id,
                'amount'        => 100.0,
                'unit_id'       => $unit->id,
            ]);

        Queue::assertPushed(SyncRecipeToSearch::class, fn($job) =>
            $job->id === $this->recipe->id
        );
    }

    // -------------------------------------------------------------------------
    // updatePivot
    // -------------------------------------------------------------------------

    public function test_update_pivot_modifies_amount_and_unit(): void
    {
        $unit       = $this->makeUnit();
        $newUnit    = $this->makeUnit(2)[1] ?? $this->makeUnit();
        $ingredient = Ingredient::factory()->create(['default_amount_unit_id' => $unit->id]);
        $this->recipe->ingredients()->attach($ingredient->id, ['amount' => 50.0, 'unit_id' => $unit->id]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('recipes.ingredients.update-pivot', [$this->recipe, $ingredient]), [
                'amount'  => 250.0,
                'unit_id' => $newUnit->id,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('recipe_ingredient', [
            'recipe_id'     => $this->recipe->id,
            'ingredient_id' => $ingredient->id,
            'amount'        => 250.0,
            'unit_id'       => $newUnit->id,
        ]);
    }

    public function test_update_pivot_accepts_partial_payload(): void
    {
        $unit       = $this->makeUnit();
        $ingredient = Ingredient::factory()->create(['default_amount_unit_id' => $unit->id]);
        $this->recipe->ingredients()->attach($ingredient->id, ['amount' => 50.0, 'unit_id' => $unit->id]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('recipes.ingredients.update-pivot', [$this->recipe, $ingredient]), [
                'amount' => 99.0,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('recipe_ingredient', [
            'recipe_id'     => $this->recipe->id,
            'ingredient_id' => $ingredient->id,
            'amount'        => 99.0,
        ]);
    }

    public function test_update_pivot_rejects_zero_or_negative_amount(): void
    {
        $unit       = $this->makeUnit();
        $ingredient = Ingredient::factory()->create(['default_amount_unit_id' => $unit->id]);
        $this->recipe->ingredients()->attach($ingredient->id, ['amount' => 50.0, 'unit_id' => $unit->id]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('recipes.ingredients.update-pivot', [$this->recipe, $ingredient]), [
                'amount' => -10.0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_update_pivot_rejects_nonexistent_unit(): void
    {
        $unit       = $this->makeUnit();
        $ingredient = Ingredient::factory()->create(['default_amount_unit_id' => $unit->id]);
        $this->recipe->ingredients()->attach($ingredient->id, ['amount' => 50.0, 'unit_id' => $unit->id]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('recipes.ingredients.update-pivot', [$this->recipe, $ingredient]), [
                'unit_id' => 99999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['unit_id']);
    }

    // -------------------------------------------------------------------------
    // detach
    // -------------------------------------------------------------------------

    public function test_detach_removes_single_ingredient(): void
    {
        $unit        = $this->makeUnit();
        $ingredients = Ingredient::factory()->count(2)->create(['default_amount_unit_id' => $unit->id]);

        foreach ($ingredients as $ingredient) {
            $this->recipe->ingredients()->attach($ingredient->id, ['amount' => 100.0, 'unit_id' => $unit->id]);
        }

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('recipes.ingredients.detach', [$this->recipe, $ingredients->first()]))
            ->assertStatus(204);

        $this->assertDatabaseMissing('recipe_ingredient', [
            'recipe_id'     => $this->recipe->id,
            'ingredient_id' => $ingredients->first()->id,
        ]);
        $this->assertDatabaseHas('recipe_ingredient', [
            'recipe_id'     => $this->recipe->id,
            'ingredient_id' => $ingredients->last()->id,
        ]);
    }

    public function test_detach_nonattached_ingredient_is_idempotent(): void
    {
        $ingredient = Ingredient::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('recipes.ingredients.detach', [$this->recipe, $ingredient]))
            ->assertStatus(204);
    }

    // -------------------------------------------------------------------------
    // detachAll
    // -------------------------------------------------------------------------

    public function test_detach_all_removes_all_ingredients(): void
    {
        $unit        = $this->makeUnit();
        $ingredients = Ingredient::factory()->count(3)->create(['default_amount_unit_id' => $unit->id]);

        foreach ($ingredients as $ingredient) {
            $this->recipe->ingredients()->attach($ingredient->id, ['amount' => 100.0, 'unit_id' => $unit->id]);
        }

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('recipes.ingredients.detach-all', $this->recipe))
            ->assertStatus(204);

        $this->assertDatabaseCount('recipe_ingredient', 0);
    }

    public function test_detach_all_when_no_ingredients_is_idempotent(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('recipes.ingredients.detach-all', $this->recipe))
            ->assertStatus(204);
    }

    protected function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }
}
