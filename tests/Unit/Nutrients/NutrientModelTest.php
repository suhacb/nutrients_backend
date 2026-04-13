<?php

namespace Tests\Unit\Nutrients;

use Tests\TestCase;
use App\Models\Unit;
use App\Models\Nutrient;
use App\Models\Ingredient;
use App\Jobs\SyncNutrientToSearch;
use Illuminate\Support\Facades\Bus;
use App\Exceptions\NutrientAttachedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\MakesUnit;

/**
 * Tests the Nutrient Eloquent model: fillable fields, casts, relationships
 * (parent/children hierarchy, canonical unit), search-sync job dispatch on
 * model events, and the guard that prevents deletion when the nutrient is
 * attached to an ingredient.
 */
class NutrientModelTest extends TestCase
{
    use RefreshDatabase, MakesUnit;

    /**
     * Asserts that the model's $fillable array matches the expected set of attributes exactly,
     * including canonical-unit and display fields.
     */
    public function test_fillable_fields(): void
    {
        $expectedFillable = [
            'source',
            'external_id',
            'name',
            'description',
            'parent_id',
            'slug',
            'canonical_unit_id',
            'iu_to_canonical_factor',
            'is_label_standard',
            'display_order',
        ];

        $this->assertEquals(
            $expectedFillable,
            (new Nutrient())->getFillable(),
            'The $fillable fields do not match the expected ones'
        );
    }

    /**
     * Asserts that the model's $casts array matches the expected type mappings exactly,
     * including boolean for `is_label_standard` and decimal:6 for `iu_to_canonical_factor`.
     */
    public function test_casts_fields(): void
    {
        $expectedCasts = [
            'id'                     => 'int',
            'created_at'             => 'datetime',
            'updated_at'             => 'datetime',
            'deleted_at'             => 'datetime',
            'is_label_standard'      => 'boolean',
            'iu_to_canonical_factor' => 'decimal:6',
        ];

        $this->assertEquals(
            $expectedCasts,
            (new Nutrient())->getCasts(),
            'The $casts fields do not match the expected ones'
        );
    }

    /**
     * Asserts that the `parent` BelongsTo relationship returns the correct parent Nutrient
     * when a child nutrient references it via `parent_id`.
     */
    public function test_parent_relationship_resolves(): void
    {
        $parent = Nutrient::factory()->create(['name' => 'Macronutrients']);
        $child  = Nutrient::factory()->create(['name' => 'Protein', 'parent_id' => $parent->id]);

        $this->assertInstanceOf(Nutrient::class, $child->parent);
        $this->assertEquals($parent->id, $child->parent->id);
    }

    /**
     * Asserts that the `children` HasMany relationship returns all child nutrients
     * that reference a given nutrient via `parent_id`.
     */
    public function test_children_relationship_resolves(): void
    {
        $parent = Nutrient::factory()->create(['name' => 'Macronutrients']);
        $child  = Nutrient::factory()->create(['name' => 'Protein', 'parent_id' => $parent->id]);

        $this->assertCount(1, $parent->children);
        $this->assertEquals($child->id, $parent->children->first()->id);
    }

    /**
     * Asserts that the `canonicalUnit` BelongsTo relationship returns the correct Unit instance
     * when a nutrient references it via `canonical_unit_id`.
     */
    public function test_canonical_unit_relationship_resolves(): void
    {
        $unit     = Unit::create(['name' => 'gram', 'abbreviation' => 'g', 'type' => 'mass']);
        $nutrient = Nutrient::factory()->create(['canonical_unit_id' => $unit->id]);

        $this->assertInstanceOf(Unit::class, $nutrient->canonicalUnit);
        $this->assertEquals($unit->id, $nutrient->canonicalUnit->id);
    }

    // test_tags_relationship_resolves will be added once NutrientTag is implemented

    /**
     * Asserts that creating a nutrient dispatches a `SyncNutrientToSearch` job
     * with action `insert` on the `nutrients` queue.
     */
    public function test_it_dispatches_insert_job_when_nutrient_is_created(): void
    {
        Bus::fake();

        $nutrient = Nutrient::factory()->create();

        Bus::assertDispatched(SyncNutrientToSearch::class, function ($job) use ($nutrient) {
            return $job->nutrient->is($nutrient) &&
                   $job->action === 'insert' &&
                   $job->queue === 'nutrients';
        });
    }

    /**
     * Asserts that updating a nutrient dispatches a `SyncNutrientToSearch` job
     * with action `update` on the `nutrients` queue.
     */
    public function test_it_dispatches_update_job_when_nutrient_is_updated(): void
    {
        Bus::fake();

        $nutrient = Nutrient::factory()->create();
        $nutrient->update(['name' => 'Updated Name']);

        Bus::assertDispatched(SyncNutrientToSearch::class, function ($job) use ($nutrient) {
            return $job->nutrient->is($nutrient) &&
                   $job->action === 'update' &&
                   $job->queue === 'nutrients';
        });
    }

    /**
     * Asserts that soft-deleting a nutrient dispatches a `SyncNutrientToSearch` job
     * with action `delete` on the `nutrients` queue.
     */
    public function test_it_dispatches_delete_job_when_nutrient_is_deleted(): void
    {
        Bus::fake();

        $nutrient = Nutrient::factory()->create();
        $nutrient->delete();

        Bus::assertDispatched(SyncNutrientToSearch::class, function ($job) use ($nutrient) {
            return $job->nutrient->is($nutrient) &&
                   $job->action === 'delete' &&
                   $job->queue === 'nutrients';
        });
    }

    /**
     * Asserts that restoring a soft-deleted nutrient dispatches a `SyncNutrientToSearch` job
     * with action `insert` on the `nutrients` queue, re-indexing it in search.
     */
    public function test_it_dispatches_insert_job_when_nutrient_is_restored(): void
    {
        Bus::fake();

        $nutrient = Nutrient::factory()->create();
        $nutrient->delete();
        $nutrient->restore();

        Bus::assertDispatched(SyncNutrientToSearch::class, function ($job) use ($nutrient) {
            return $job->nutrient->is($nutrient) &&
                   $job->action === 'insert' &&
                   $job->queue === 'nutrients';
        });
    }

    /**
     * Asserts that attempting to delete a nutrient that is attached to an ingredient
     * throws `NutrientAttachedException` and leaves the nutrient record intact.
     */
    public function test_it_prevents_delete_if_attached_to_ingredient(): void
    {
        $this->expectException(NutrientAttachedException::class);
        $this->expectExceptionMessage('Cannot delete nutrient: it is attached to one or more ingredients.');

        $nutrient   = Nutrient::factory()->create();
        $unit       = $this->makeUnit();
        $ingredient = Ingredient::factory()->create();

        $ingredient->nutrients()->attach($nutrient->id, [
            'amount'         => 10,
            'amount_unit_id' => $unit->id,
        ]);

        $nutrient->delete();

        $this->assertDatabaseHas('nutrients', ['id' => $nutrient->id]);
    }
}
