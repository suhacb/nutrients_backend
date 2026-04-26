<?php

namespace Tests\Feature\Nutrients;

use Tests\TestCase;
use Tests\MakesUnit;
use App\Models\Nutrient;
use App\Models\Source;
use Tests\LoginTestUser;
use App\Models\Ingredient;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Feature tests for the NutrientsController.
 *
 * Covers index pagination, show, store, update, delete (including soft delete),
 * the conflict guard when a nutrient is attached to an ingredient, and
 * store validation.  Each test runs against a fresh database, authenticates
 * via the external auth service, and fakes the job queue so no Zinc sync
 * jobs are dispatched.
 */
class NutrientsControllerTest extends TestCase
{
    use RefreshDatabase, LoginTestUser, MakesUnit;

    protected Source $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->login();
        Queue::fake();
        $this->source = Source::factory()->create();
    }

    /**
     *
     * Verifies that GET /api/nutrients returns a paginated list of nutrients,
     * that the pagination meta keys are present, that the first page contains
     * the expected number of items, and that a specific page returns only the
     * remaining items when the dataset does not fill the page completely.
     */
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

    /**
     *
     * Verifies that GET /api/nutrients/{id} returns a 200 response whose JSON
     * body contains the nutrient's id and name.
     */
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

    /**
     *
     * Verifies that POST /api/nutrients with a valid payload returns a 201
     * response whose JSON body mirrors the submitted data, and that the record
     * is persisted in the database.
     */
    public function test_store_creates_nutrient(): void
    {
        $payload = [
            'source_id'   => $this->source->id,
            'external_id' => '101',
            'name'        => 'Protein',
            'description' => 'Test nutrient',
        ];

        $response = $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('nutrients.store'), $payload);

        $response->assertStatus(201)->assertJson($payload);

        $this->assertDatabaseHas('nutrients', ['name' => 'Protein']);
    }

    /**
     *
     * Verifies that PUT /api/nutrients/{id} updates the nutrient's fields,
     * returns a 200 response containing the new values, and persists the
     * change in the database.
     */
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

    /**
     *
     * Verifies that DELETE /api/nutrients/{id} returns a 204 No Content
     * response and soft-deletes the record (row remains in the table with a
     * non-null deleted_at value).
     */
    public function test_deletes_nutrient(): void
    {
        $nutrient = Nutrient::factory()->create();
        $response = $this->withHeaders($this->makeAuthRequestHeader())->deleteJson(route('nutrients.delete', $nutrient));

        $response->assertStatus(204);

        $this->assertSoftDeleted('nutrients', ['id' => $nutrient->id]);
    }

    /**
     *
     * Verifies that attempting to delete a nutrient that is attached to an
     * ingredient returns a 409 Conflict with an explanatory message, and that
     * the nutrient record is not removed from the database.
     */
    public function test_cannot_delete_nutrient_if_attached_to_ingredient(): void
    {
        // Create a nutrient
        $nutrient = Nutrient::factory()->create();

        // Create a unit and ingredient
        $unit = $this->makeUnit();
        $ingredient = Ingredient::factory()->create();

        // Attach nutrient to ingredient
        $ingredient->nutrients()->attach($nutrient->id, [
            'amount' => 10,
            'amount_unit_id' => $unit->id,
        ]);

        // Attempt to delete the nutrient via the controller
        $response = $this->withHeaders($this->makeAuthRequestHeader())->deleteJson(route('nutrients.delete', $nutrient));

        $response->assertStatus(409)->assertJson([
            'message' => 'Cannot delete nutrient: it is attached to one or more ingredients.'
        ]);

        // Assert the nutrient still exists
        $this->assertDatabaseHas('nutrients', ['id' => $nutrient->id]);
    }

    /**

     *
     * Verifies that POST /api/nutrients with an empty body returns a 422
     * Unprocessable Entity response with validation errors for both the
     * required `name` and `source` fields.
     */
    public function test_store_requires_name_and_source_id(): void
    {
        $response = $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('nutrients.store'), []);

        $response->assertStatus(422)->assertJsonValidationErrors([
            'source_id',
            'name',
        ]);
    }

    /**
     *
     * Verifies that POST /api/nutrients with a valid parent_id returns a 201
     * response that includes parent_id, and that the DB row stores the correct
     * parent relationship.
     */
    public function test_store_creates_nutrient_with_parent(): void
    {
        $parent = Nutrient::factory()->create();

        $payload = [
            'source_id' => $this->source->id,
            'name'      => 'Child Nutrient',
            'parent_id' => $parent->id,
        ];

        $response = $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('nutrients.store'), $payload);

        $response->assertStatus(201)->assertJsonFragment(['parent_id' => $parent->id]);
        $this->assertDatabaseHas('nutrients', ['name' => 'Child Nutrient', 'parent_id' => $parent->id]);
    }

    /**
     *
     * Verifies that POST /api/nutrients with a parent_id that does not exist
     * returns a 422 response with a validation error on parent_id.
     */
    public function test_store_rejects_nonexistent_parent_id(): void
    {
        $payload = [
            'source_id' => $this->source->id,
            'name'      => 'Child Nutrient',
            'parent_id' => 99999,
        ];

        $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('nutrients.store'), $payload)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['parent_id']);
    }

    /**

     *
     * Verifies that POST /api/nutrients with a non-integer parent_id returns a
     * 422 response with a validation error on parent_id.
     */
    public function test_store_rejects_non_integer_parent_id(): void
    {
        $payload = [
            'source_id' => $this->source->id,
            'name'      => 'Child Nutrient',
            'parent_id' => 'not-an-id',
        ];

        $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('nutrients.store'), $payload)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['parent_id']);
    }

    /**

     *
     * Verifies that PUT /api/nutrients/{id} with a parent_id that does not
     * exist returns a 422 response with a validation error on parent_id.
     */
    public function test_update_rejects_nonexistent_parent_id(): void
    {
        $nutrient = Nutrient::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
             ->putJson(route('nutrients.update', $nutrient), ['parent_id' => 99999])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['parent_id']);
    }

    /**

     *
     * Verifies that attempting to delete a nutrient that has non-deleted
     * children returns a 409 Conflict with an explanatory message, and that
     * the parent record is not removed from the database.
     */
    public function test_cannot_delete_nutrient_with_children(): void
    {
        $parent = Nutrient::factory()->create();
        Nutrient::factory()->create(['parent_id' => $parent->id]);

        $response = $this->withHeaders($this->makeAuthRequestHeader())->deleteJson(route('nutrients.delete', $parent));

        $response->assertStatus(409)->assertJson([
            'message' => 'Cannot delete nutrient: it has one or more non-deleted children.',
        ]);

        $this->assertDatabaseHas('nutrients', ['id' => $parent->id]);
    }

    /**

     *
     * Verifies that POST /api/nutrients with a valid canonical_unit_id returns
     * a 201 response that includes canonical_unit_id, and that the DB row
     * stores the correct unit relationship.
     */
    public function test_store_creates_nutrient_with_canonical_unit(): void
    {
        $unit = $this->makeUnit();

        $payload = [
            'source_id'         => $this->source->id,
            'name'              => 'Vitamin D',
            'canonical_unit_id' => $unit->id,
        ];

        $response = $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('nutrients.store'), $payload);

        $response->assertStatus(201)->assertJsonFragment(['canonical_unit_id' => $unit->id]);
        $this->assertDatabaseHas('nutrients', ['name' => 'Vitamin D', 'canonical_unit_id' => $unit->id]);
    }

    /**

     *
     * Verifies that POST /api/nutrients with a canonical_unit_id that does not
     * exist returns a 422 response with a validation error on canonical_unit_id.
     */
    public function test_store_rejects_nonexistent_canonical_unit_id(): void
    {
        $payload = [
            'source_id'         => $this->source->id,
            'name'              => 'Vitamin D',
            'canonical_unit_id' => 99999,
        ];

        $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('nutrients.store'), $payload)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['canonical_unit_id']);
    }

    /**

     *
     * Verifies that POST /api/nutrients with a non-integer canonical_unit_id
     * returns a 422 response with a validation error on canonical_unit_id.
     */
    public function test_store_rejects_non_integer_canonical_unit_id(): void
    {
        $payload = [
            'source_id'         => $this->source->id,
            'name'              => 'Vitamin D',
            'canonical_unit_id' => 'gram',
        ];

        $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('nutrients.store'), $payload)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['canonical_unit_id']);
    }

    /**

     *
     * Verifies that PUT /api/nutrients/{id} with a canonical_unit_id that does
     * not exist returns a 422 response with a validation error on
     * canonical_unit_id.
     */
    public function test_update_rejects_nonexistent_canonical_unit_id(): void
    {
        $nutrient = Nutrient::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
             ->putJson(route('nutrients.update', $nutrient), ['canonical_unit_id' => 99999])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['canonical_unit_id']);
    }

    /**

     *
     * Verifies that PUT /api/nutrients/{id} with a valid canonical_unit_id
     * returns a 200 response that includes the new value and persists the
     * change in the database.
     */
    public function test_update_sets_canonical_unit(): void
    {
        $nutrient = Nutrient::factory()->create();
        $unit     = $this->makeUnit();

        $this->withHeaders($this->makeAuthRequestHeader())
             ->putJson(route('nutrients.update', $nutrient), ['canonical_unit_id' => $unit->id])
             ->assertStatus(200)
             ->assertJsonFragment(['canonical_unit_id' => $unit->id]);

        $this->assertDatabaseHas('nutrients', ['id' => $nutrient->id, 'canonical_unit_id' => $unit->id]);
    }

    /**

     *
     * Verifies that GET /api/nutrients/{id} for a nutrient that has a
     * canonical_unit includes a nested canonical_unit object whose id matches
     * the stored unit.
     */
    public function test_show_includes_canonical_unit(): void
    {
        $unit     = $this->makeUnit();
        $nutrient = Nutrient::factory()->create(['canonical_unit_id' => $unit->id]);

        $this->withHeaders($this->makeAuthRequestHeader())
             ->getJson(route('nutrients.show', $nutrient))
             ->assertStatus(200)
             ->assertJsonPath('canonical_unit.id', $unit->id);
    }

    /**

     *
     * Verifies that POST /api/nutrients with a slug that is already taken by
     * another nutrient returns a 422 response with a validation error on slug.
     */
    public function test_store_rejects_duplicate_slug(): void
    {
        Nutrient::factory()->create(['slug' => 'protein']);

        $payload = [
            'source_id' => $this->source->id,
            'name'      => 'Protein Duplicate',
            'slug'      => 'protein',
        ];

        $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('nutrients.store'), $payload)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['slug']);
    }

    /**

     *
     * Verifies that PUT /api/nutrients/{id} with a slug that is already taken
     * by a different nutrient returns a 422 response with a validation error
     * on slug.
     */
    public function test_update_rejects_duplicate_slug(): void
    {
        Nutrient::factory()->create(['slug' => 'protein']);
        $nutrient = Nutrient::factory()->create(['slug' => 'fat']);

        $this->withHeaders($this->makeAuthRequestHeader())
             ->putJson(route('nutrients.update', $nutrient), ['slug' => 'protein'])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['slug']);
    }

    /**

     *
     * Verifies that POST /api/nutrients with a non-numeric
     * iu_to_canonical_factor returns a 422 response with a validation error on
     * that field.
     */
    public function test_store_rejects_non_numeric_iu_to_canonical_factor(): void
    {
        $payload = [
            'source_id'              => $this->source->id,
            'name'                   => 'Vitamin A',
            'iu_to_canonical_factor' => 'not-a-number',
        ];

        $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('nutrients.store'), $payload)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['iu_to_canonical_factor']);
    }

    /**

     *
     * Verifies that PUT /api/nutrients/{id} with a non-numeric
     * iu_to_canonical_factor returns a 422 response with a validation error on
     * that field.
     */
    public function test_update_rejects_non_numeric_iu_to_canonical_factor(): void
    {
        $nutrient = Nutrient::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
             ->putJson(route('nutrients.update', $nutrient), ['iu_to_canonical_factor' => 'not-a-number'])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['iu_to_canonical_factor']);
    }

    /**

     *
     * Verifies that POST /api/nutrients with a non-boolean is_label_standard
     * returns a 422 response with a validation error on that field.
     */
    public function test_store_rejects_non_boolean_is_label_standard(): void
    {
        $payload = [
            'source_id'         => $this->source->id,
            'name'              => 'Protein',
            'is_label_standard' => 'yes',
        ];

        $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('nutrients.store'), $payload)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['is_label_standard']);
    }

    /**

     *
     * Verifies that PUT /api/nutrients/{id} with a non-boolean
     * is_label_standard returns a 422 response with a validation error on that
     * field.
     */
    public function test_update_rejects_non_boolean_is_label_standard(): void
    {
        $nutrient = Nutrient::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
             ->putJson(route('nutrients.update', $nutrient), ['is_label_standard' => 'yes'])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['is_label_standard']);
    }

    /**

     *
     * Verifies that POST /api/nutrients with a non-integer display_order
     * returns a 422 response with a validation error on that field.
     */
    public function test_store_rejects_non_integer_display_order(): void
    {
        $payload = [
            'source_id'     => $this->source->id,
            'name'          => 'Protein',
            'display_order' => 'first',
        ];

        $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('nutrients.store'), $payload)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['display_order']);
    }

    /**

     *
     * Verifies that PUT /api/nutrients/{id} with a non-integer display_order
     * returns a 422 response with a validation error on that field.
     */
    public function test_update_rejects_non_integer_display_order(): void
    {
        $nutrient = Nutrient::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
             ->putJson(route('nutrients.update', $nutrient), ['display_order' => 'first'])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['display_order']);
    }

    /**

     *
     * Verifies that POST /api/nutrients accepts all new optional fields
     * (parent_id, slug, canonical_unit_id, iu_to_canonical_factor,
     * is_label_standard, display_order) in a single request, returns 201,
     * and persists every field correctly in the database.
     */
    public function test_store_accepts_all_new_optional_fields(): void
    {
        $parent = Nutrient::factory()->create();
        $unit   = $this->makeUnit();

        $payload = [
            'source_id'              => $this->source->id,
            'name'                   => 'Vitamin D',
            'parent_id'              => $parent->id,
            'slug'                   => 'vitamin-d',
            'canonical_unit_id'      => $unit->id,
            'iu_to_canonical_factor' => '0.025',
            'is_label_standard'      => true,
            'display_order'          => 5,
        ];

        $response = $this->withHeaders($this->makeAuthRequestHeader())->postJson(route('nutrients.store'), $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('nutrients', [
            'name'              => 'Vitamin D',
            'parent_id'         => $parent->id,
            'slug'              => 'vitamin-d',
            'canonical_unit_id' => $unit->id,
            'is_label_standard' => true,
            'display_order'     => 5,
        ]);
    }

    protected function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }
}
