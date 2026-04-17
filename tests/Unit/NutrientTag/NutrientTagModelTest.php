<?php

namespace Tests\Unit\NutrientTag;

use App\Models\NutrientTag;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests the NutrientTag Eloquent model: table name, fillable fields, casts,
 * nullable columns, and the unique constraint on slug.
 */
class NutrientTagModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Asserts that the model targets the `nutrient_tags` table and declares exactly the expected fillable fields.
     */
    public function test_it_has_correct_table_and_fillable_fields(): void
    {
        $tag = new NutrientTag();

        $this->assertEquals('nutrient_tags', $tag->getTable());
        $this->assertEquals(['name', 'slug', 'description'], $tag->getFillable());
    }

    /**
     * Asserts that the model's $casts array matches the expected type mappings exactly.
     * NutrientTag has no soft deletes, so there is no `deleted_at` cast.
     */
    public function test_casts_fields(): void
    {
        $expectedCasts = [
            'id'         => 'int',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];

        $this->assertEquals(
            $expectedCasts,
            (new NutrientTag())->getCasts(),
            'The $casts fields do not match the expected ones'
        );
    }

    /**
     * Asserts that a NutrientTag can be created and retrieved with all fields intact.
     */
    public function test_it_can_be_created_with_all_fields(): void
    {
        $tag = NutrientTag::create([
            'name'        => 'Vitamins',
            'slug'        => 'vitamins',
            'description' => 'Fat- and water-soluble vitamins.',
        ]);

        $fresh = NutrientTag::find($tag->id);

        $this->assertEquals('Vitamins', $fresh->name);
        $this->assertEquals('vitamins', $fresh->slug);
        $this->assertEquals('Fat- and water-soluble vitamins.', $fresh->description);
    }

    /**
     * Asserts that `description` is optional and stores NULL when omitted.
     */
    public function test_description_is_nullable(): void
    {
        $tag = NutrientTag::create(['name' => 'Minerals', 'slug' => 'minerals']);

        $this->assertNull(NutrientTag::find($tag->id)->description);
    }

    /**
     * Asserts that the unique constraint on `slug` is enforced at the database level.
     */
    public function test_slug_must_be_unique(): void
    {
        NutrientTag::create(['name' => 'Vitamins', 'slug' => 'vitamins']);

        $this->expectException(QueryException::class);

        NutrientTag::create(['name' => 'Vitamins Duplicate', 'slug' => 'vitamins']);
    }
}
