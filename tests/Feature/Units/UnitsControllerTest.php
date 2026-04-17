<?php

namespace Tests\Feature\Units;

use Tests\TestCase;
use App\Models\Unit;
use Tests\LoginTestUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Feature tests for the Units REST API endpoints.
 *
 * Covers pagination, single-resource retrieval, canonical relationship exposure,
 * create/update/delete lifecycle, and request validation rules introduced
 * by the 31-canonical-model feature.
 */
class UnitsControllerTest extends TestCase
{
    use RefreshDatabase, LoginTestUser;

    /**
     * Authenticate via the external auth backend before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->login();
    }

    /**
     * Asserts that GET /units returns a paginated response with 25 items per page
     * and correct pagination metadata (current_page, last_page, per_page, total).
     */
    public function test_index_returns_paginated_units(): void
    {
        // Factory has 22 fixed entries; create unique ones programmatically
        foreach (range(1, 30) as $i) {
            Unit::create(['name' => "Unit {$i}", 'abbreviation' => "U{$i}", 'type' => 'mass']);
        }

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('units.index'));

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
            ->getJson(route('units.index') . '?page=2')
            ->json();

        $this->assertCount(5, $page2['data']);
        $this->assertEquals(2, $page2['current_page']);
    }

    /**
     * Asserts that GET /units/{id} returns the correct unit with its core fields.
     */
    public function test_show_returns_single_unit(): void
    {
        $unit = Unit::factory()->create();

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('units.show', $unit));

        $response->assertStatus(200)
            ->assertJson([
                'id'           => $unit->id,
                'name'         => $unit->name,
                'abbreviation' => $unit->abbreviation,
                'type'         => $unit->type,
            ]);
    }

    /**
     * Asserts that the show response includes the `base_unit` object on derived units
     * and the `derived_units` array on base units.
     */
    public function test_show_includes_base_unit_and_derived_units(): void
    {
        $base = Unit::create(['name' => 'gram', 'abbreviation' => 'g', 'type' => 'mass']);
        $derived = Unit::create([
            'name'           => 'kilogram',
            'abbreviation'   => 'kg',
            'type'           => 'mass',
            'base_unit_id'   => $base->id,
            'to_base_factor' => '1000.0000000000',
        ]);

        // Base unit exposes its derived units
        $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('units.show', $base))
            ->assertStatus(200)
            ->assertJsonPath('derived_units.0.id', $derived->id);

        // Derived unit exposes its base unit
        $this->withHeaders($this->makeAuthRequestHeader())
            ->getJson(route('units.show', $derived))
            ->assertStatus(200)
            ->assertJsonPath('base_unit.id', $base->id);
    }

    /**
     * Asserts that POST /units persists a new unit and returns 201 with the created resource.
     */
    public function test_store_creates_unit(): void
    {
        $payload = ['name' => 'gram', 'abbreviation' => 'g', 'type' => 'mass'];

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('units.store'), $payload)
            ->assertStatus(201)
            ->assertJson($payload);

        $this->assertDatabaseHas('units', $payload);
    }

    /**
     * Asserts that POST /units accepts `base_unit_id` and `to_base_factor`, persisting the
     * canonical relationship correctly.
     */
    public function test_store_creates_unit_with_base_unit(): void
    {
        $base = Unit::create(['name' => 'gram', 'abbreviation' => 'g', 'type' => 'mass']);

        $payload = [
            'name'           => 'kilogram',
            'abbreviation'   => 'kg',
            'type'           => 'mass',
            'base_unit_id'   => $base->id,
            'to_base_factor' => '1000.0000000000',
        ];

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('units.store'), $payload)
            ->assertStatus(201)
            ->assertJson(['name' => 'kilogram', 'base_unit_id' => $base->id]);

        $this->assertDatabaseHas('units', ['name' => 'kilogram', 'base_unit_id' => $base->id]);
    }

    /**
     * Asserts that PUT /units/{id} updates the specified fields and returns the updated resource.
     */
    public function test_update_modifies_unit(): void
    {
        $unit = Unit::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('units.update', $unit), ['name' => 'Updated Name'])
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'name' => 'Updated Name']);
    }

    /**
     * Asserts that DELETE /units/{id} returns 204 and removes the unit from the database.
     */
    public function test_delete_removes_unit(): void
    {
        $unit = Unit::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('units.delete', $unit))
            ->assertStatus(204);

        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
    }

    /**
     * Asserts that deleting a base unit sets `base_unit_id` to NULL on its derived units
     * rather than cascading the delete, preserving orphaned units in the database.
     */
    public function test_delete_sets_base_unit_id_null_on_derived_units(): void
    {
        $base = Unit::create(['name' => 'gram', 'abbreviation' => 'g', 'type' => 'mass']);
        $derived = Unit::create([
            'name' => 'kilogram', 'abbreviation' => 'kg',
            'type' => 'mass', 'base_unit_id' => $base->id,
        ]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('units.delete', $base))
            ->assertStatus(204);

        $this->assertDatabaseMissing('units', ['id' => $base->id]);
        $this->assertDatabaseHas('units', ['id' => $derived->id, 'base_unit_id' => null]);
    }

    /**
     * Asserts that POST /units returns 422 with field-level validation errors when
     * `name` and `abbreviation` are omitted.
     */
    public function test_store_requires_name_and_abbreviation(): void
    {
        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('units.store'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'abbreviation']);

        $this->assertEquals('The unit name is required.',         $response->json('errors.name.0'));
        $this->assertEquals('The unit abbreviation is required.', $response->json('errors.abbreviation.0'));
    }

    /**
     * Asserts that POST /units returns 422 when `type` is not one of the allowed enum values
     * (mass, energy, volume, other).
     */
    public function test_store_rejects_invalid_type(): void
    {
        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('units.store'), [
                'name' => 'gram', 'abbreviation' => 'g', 'type' => 'weight',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['type']);
    }

    /**
     * Asserts that POST /units returns 422 when `base_unit_id` references a unit that does not exist.
     */
    public function test_store_rejects_nonexistent_base_unit_id(): void
    {
        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('units.store'), [
                'name' => 'gram', 'abbreviation' => 'g', 'type' => 'mass', 'base_unit_id' => 99999,
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['base_unit_id']);
    }

    /**
     * Asserts that POST /units returns 422 when the (name, abbreviation, type) combination
     * already exists in the database, enforcing the unique constraint at the application layer.
     */
    public function test_store_rejects_duplicate_name_abbreviation_type(): void
    {
        Unit::create(['name' => 'gram', 'abbreviation' => 'g', 'type' => 'mass']);

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('units.store'), [
                'name' => 'gram', 'abbreviation' => 'g', 'type' => 'mass',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    /**
     * Asserts that POST /units accepts all four valid enum values for `type`
     * (mass, energy, volume, other) without returning a validation error.
     */
    public function test_store_accepts_all_valid_type_values(): void
    {
        foreach (['mass', 'energy', 'volume', 'other'] as $i => $type) {
            $this->withHeaders($this->makeAuthRequestHeader())
                ->postJson(route('units.store'), [
                    'name'         => "Unit {$type}",
                    'abbreviation' => "u{$i}",
                    'type'         => $type,
                ])
                ->assertStatus(201);
        }
    }

    /**
     * Asserts that the unique constraint on `name` is scoped to (name, abbreviation, type),
     * so the same name and abbreviation with a different type is accepted.
     */
    public function test_store_allows_same_name_and_abbreviation_with_different_type(): void
    {
        Unit::create(['name' => 'gram', 'abbreviation' => 'g', 'type' => 'mass']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('units.store'), [
                'name'         => 'gram',
                'abbreviation' => 'g',
                'type'         => 'other',
            ])
            ->assertStatus(201);
    }

    /**
     * Asserts that POST /units returns 422 when `to_base_factor` is a non-numeric string.
     */
    public function test_store_rejects_non_numeric_to_base_factor(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('units.store'), [
                'name'           => 'gram',
                'abbreviation'   => 'g',
                'type'           => 'mass',
                'to_base_factor' => 'not-a-number',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to_base_factor']);
    }

    /**
     * Asserts that the custom error message is returned when an invalid `type` value is supplied
     * on store.
     */
    public function test_store_type_error_message(): void
    {
        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('units.store'), [
                'name'         => 'gram',
                'abbreviation' => 'g',
                'type'         => 'weight',
            ]);

        $response->assertStatus(422);
        $this->assertEquals(
            'The type must be one of: mass, energy, volume, other.',
            $response->json('errors.type.0')
        );
    }

    /**
     * Asserts that PUT /units/{id} returns 422 when `type` is not one of the allowed enum values.
     */
    public function test_update_rejects_invalid_type(): void
    {
        $unit = Unit::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('units.update', $unit), ['type' => 'weight'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    /**
     * Asserts that PUT /units/{id} returns 422 when `base_unit_id` references a unit that does not exist.
     */
    public function test_update_rejects_nonexistent_base_unit_id(): void
    {
        $unit = Unit::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('units.update', $unit), ['base_unit_id' => 99999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['base_unit_id']);
    }

    /**
     * Asserts that PUT /units/{id} accepts and persists `base_unit_id` and `to_base_factor`.
     */
    public function test_update_accepts_canonical_fields(): void
    {
        $base    = Unit::create(['name' => 'gram',     'abbreviation' => 'g',  'type' => 'mass']);
        $derived = Unit::create(['name' => 'kilogram', 'abbreviation' => 'kg', 'type' => 'mass']);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->putJson(route('units.update', $derived), [
                'base_unit_id'   => $base->id,
                'to_base_factor' => '1000.0000000000',
            ])
            ->assertStatus(200)
            ->assertJsonFragment(['base_unit_id' => $base->id]);

        $this->assertDatabaseHas('units', [
            'id'           => $derived->id,
            'base_unit_id' => $base->id,
        ]);
    }

    /**
     * Log out and tear down after each test.
     */
    protected function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }
}