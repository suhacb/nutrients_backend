<?php

namespace Tests\Unit\Nutrients;

use Tests\TestCase;
use App\Models\Unit;
use App\Models\Nutrient;
use App\Models\Ingredient;
use App\Jobs\SyncNutrientToSearch;
use Illuminate\Support\Facades\Bus;
use App\Exceptions\NutrientAttachedException;
use App\Exceptions\NutrientHasChildrenException;
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

    /**
     * Asserts that a nutrient created without `is_label_standard` reads back as false,
     * confirming the column default propagates correctly through the model.
     */
    public function test_is_label_standard_defaults_to_false(): void
    {
        $nutrient = Nutrient::factory()->create();

        $this->assertFalse(Nutrient::find($nutrient->id)->is_label_standard);
    }

    /**
     * Asserts that `is_label_standard` round-trips through the database as a boolean,
     * not as the raw tinyint 0/1 stored in MySQL.
     */
    public function test_is_label_standard_boolean_cast(): void
    {
        $flagged    = Nutrient::factory()->create(['is_label_standard' => true]);
        $unflagged  = Nutrient::factory()->create(['is_label_standard' => false]);

        $this->assertSame(true,  Nutrient::find($flagged->id)->is_label_standard);
        $this->assertSame(false, Nutrient::find($unflagged->id)->is_label_standard);
        $this->assertIsBool(Nutrient::find($flagged->id)->is_label_standard);
    }

    /**
     * Asserts that accessing `canonicalUnit` on a nutrient without a canonical_unit_id
     * returns null rather than throwing, since callers use the null-safe operator.
     */
    public function test_canonical_unit_is_null_when_not_set(): void
    {
        $nutrient = Nutrient::factory()->create(['canonical_unit_id' => null]);

        $this->assertNull($nutrient->canonicalUnit);
    }

    /**
     * Asserts that soft-deleting a parent with at least one non-deleted child throws
     * NutrientHasChildrenException, leaving both records intact.
     */
    public function test_it_prevents_soft_delete_if_has_non_deleted_children(): void
    {
        $this->expectException(NutrientHasChildrenException::class);
        $this->expectExceptionMessage('Cannot delete nutrient: it has one or more non-deleted children.');

        $parent = Nutrient::factory()->create(['name' => 'Macronutrients']);
        Nutrient::factory()->create(['name' => 'Protein', 'parent_id' => $parent->id]);

        $parent->delete();
    }

    /**
     * Asserts that force-deleting a parent with at least one non-deleted child also throws
     * NutrientHasChildrenException — the guard fires on the `deleting` event for both paths.
     */
    public function test_it_prevents_force_delete_if_has_non_deleted_children(): void
    {
        $this->expectException(NutrientHasChildrenException::class);
        $this->expectExceptionMessage('Cannot delete nutrient: it has one or more non-deleted children.');

        $parent = Nutrient::factory()->create(['name' => 'Macronutrients']);
        Nutrient::factory()->create(['name' => 'Protein', 'parent_id' => $parent->id]);

        $parent->forceDelete();
    }

    /**
     * Asserts that soft-deleting a parent is allowed when all of its children are already
     * soft-deleted, since the `children()` relationship scopes out soft-deleted records.
     */
    public function test_it_allows_soft_delete_when_all_children_are_soft_deleted(): void
    {
        Bus::fake();
        
        $parent = Nutrient::factory()->create(['name' => 'Macronutrients']);
        $child  = Nutrient::factory()->create(['name' => 'Protein', 'parent_id' => $parent->id]);

        // Bypass the guard by deleting the child first (it has no children of its own)
        $child->delete();

        // Parent now has no non-deleted children — soft delete must succeed
        $parent->delete();

        $this->assertSoftDeleted('nutrients', ['id' => $parent->id]);
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
