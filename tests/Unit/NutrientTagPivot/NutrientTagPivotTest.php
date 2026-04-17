<?php

namespace Tests\Unit\NutrientTagPivot;

use App\Models\Nutrient;
use App\Models\NutrientTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NutrientTagPivotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /**
     * Asserts the tags() method returns a BelongsToMany relation through the correct pivot table.
     */
    public function test_nutrient_has_tags_relation(): void
    {
        $nutrient = Nutrient::factory()->create();
        $relation = $nutrient->tags();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class, $relation);
        $this->assertEquals('nutrient_nutrient_tag', $relation->getTable());
    }

    /**
     * Asserts the nutrients() method on NutrientTag is the inverse BelongsToMany.
     */
    public function test_nutrient_tag_has_nutrients_relation(): void
    {
        $tag = NutrientTag::factory()->create();
        $relation = $tag->nutrients();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class, $relation);
        $this->assertEquals('nutrient_nutrient_tag', $relation->getTable());
    }

    /**
     * Asserts a single tag can be attached to a nutrient and is retrievable.
     */
    public function test_can_attach_a_tag_to_a_nutrient(): void
    {
        $nutrient = Nutrient::factory()->create();
        $tag = NutrientTag::factory()->create();

        $nutrient->tags()->attach($tag);

        $this->assertCount(1, $nutrient->fresh()->tags);
        $this->assertEquals($tag->id, $nutrient->fresh()->tags->first()->id);
    }

    /**
     * Asserts multiple tags can be attached and are all returned.
     */
    public function test_can_attach_multiple_tags(): void
    {
        $nutrient = Nutrient::factory()->create();
        $tags = NutrientTag::factory()->count(3)->create();

        $nutrient->tags()->attach($tags->pluck('id'));

        $this->assertCount(3, $nutrient->fresh()->tags);
    }

    /**
     * Asserts a tag can be detached, leaving the remaining tags intact.
     */
    public function test_can_detach_a_tag(): void
    {
        $nutrient = Nutrient::factory()->create();
        $tags = NutrientTag::factory()->count(2)->create();
        $nutrient->tags()->attach($tags->pluck('id'));

        $nutrient->tags()->detach($tags->first()->id);

        $remaining = $nutrient->fresh()->tags;
        $this->assertCount(1, $remaining);
        $this->assertEquals($tags->last()->id, $remaining->first()->id);
    }

    /**
     * Asserts sync replaces all pivot rows with the given set.
     */
    public function test_can_sync_tags(): void
    {
        $nutrient = Nutrient::factory()->create();
        $initial = NutrientTag::factory()->count(2)->create();
        $nutrient->tags()->attach($initial->pluck('id'));

        $newTags = NutrientTag::factory()->count(3)->create();
        $nutrient->tags()->sync($newTags->pluck('id'));

        $synced = $nutrient->fresh()->tags->pluck('id')->sort()->values();
        $this->assertEquals($newTags->pluck('id')->sort()->values(), $synced);
    }

    /**
     * Asserts syncing with an empty array removes all pivot rows.
     */
    public function test_sync_with_empty_set_removes_all_tags(): void
    {
        $nutrient = Nutrient::factory()->create();
        $nutrient->tags()->attach(NutrientTag::factory()->count(2)->create()->pluck('id'));

        $nutrient->tags()->sync([]);

        $this->assertCount(0, $nutrient->fresh()->tags);
    }

    /**
     * Asserts the inverse relation returns nutrients attached to a given tag.
     */
    public function test_nutrient_tag_can_access_its_nutrients(): void
    {
        $tag = NutrientTag::factory()->create();
        $nutrients = Nutrient::factory()->count(2)->create();
        $tag->nutrients()->attach($nutrients->pluck('id'));

        $this->assertCount(2, $tag->fresh()->nutrients);
    }

    /**
     * Asserts the pivot table carries no timestamps (no created_at/updated_at on pivot model).
     */
    public function test_pivot_has_no_timestamps(): void
    {
        $nutrient = Nutrient::factory()->create();
        $tag = NutrientTag::factory()->create();
        $nutrient->tags()->attach($tag);

        $pivot = $nutrient->fresh()->tags->first()->pivot;

        $this->assertFalse(isset($pivot->created_at), 'Pivot should not have created_at');
        $this->assertFalse(isset($pivot->updated_at), 'Pivot should not have updated_at');
    }

    /**
     * Asserts a nutrient can be attached to many tags and each tag sees it in its nutrients collection.
     */
    public function test_pivot_is_accessible_from_both_sides(): void
    {
        $nutrient = Nutrient::factory()->create();
        $tags = NutrientTag::factory()->count(2)->create();
        $nutrient->tags()->attach($tags->pluck('id'));

        foreach ($tags as $tag) {
            $this->assertTrue(
                $tag->fresh()->nutrients->contains('id', $nutrient->id),
                "Tag {$tag->id} should contain nutrient {$nutrient->id}"
            );
        }
    }

    /**
     * Asserts that soft-deleting a nutrient does NOT remove its pivot rows.
     * Hard delete (migration cascade) is covered by NutrientTagPivotMigrationTest.
     */
    public function test_soft_deleting_nutrient_does_not_remove_pivot_rows(): void
    {
        $nutrient = Nutrient::factory()->create();
        $tag = NutrientTag::factory()->create();
        $nutrient->tags()->attach($tag);

        $nutrient->delete();

        $this->assertEquals(
            1,
            \Illuminate\Support\Facades\DB::table('nutrient_nutrient_tag')
                ->where('nutrient_id', $nutrient->id)
                ->count(),
            'Soft delete should not cascade to pivot rows'
        );
    }
}
