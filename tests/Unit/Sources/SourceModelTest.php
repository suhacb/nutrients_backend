<?php

namespace Tests\Unit\Sources;

use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SourceModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Asserts that the model targets the `sources` table and declares exactly the expected fillable fields.
     */
    public function test_it_has_correct_table_and_fillable_fields(): void
    {
        $source = new Source();

        $this->assertEquals('sources', $source->getTable());
        $this->assertEquals(['name', 'slug', 'url', 'description'], $source->getFillable());
    }

    /**
     * Asserts that a Source can be created and retrieved with all fields intact.
     */
    public function test_it_can_be_created_with_all_fields(): void
    {
        $source = Source::create([
            'name'        => 'USDA FoodData Central',
            'slug'        => 'usda',
            'url'         => 'https://fdc.nal.usda.gov',
            'description' => 'The USDA national nutrient database.',
        ]);

        $fresh = Source::find($source->id);

        $this->assertEquals('USDA FoodData Central', $fresh->name);
        $this->assertEquals('usda', $fresh->slug);
        $this->assertEquals('https://fdc.nal.usda.gov', $fresh->url);
        $this->assertEquals('The USDA national nutrient database.', $fresh->description);
    }

    /**
     * Asserts that `url` is optional and stores NULL when omitted.
     */
    public function test_url_is_nullable(): void
    {
        $source = Source::create(['name' => 'EFSA', 'slug' => 'efsa']);

        $this->assertNull(Source::find($source->id)->url);
    }

    /**
     * Asserts that `description` is optional and stores NULL when omitted.
     */
    public function test_description_is_nullable(): void
    {
        $source = Source::create(['name' => 'EFSA', 'slug' => 'efsa']);

        $this->assertNull(Source::find($source->id)->description);
    }

    /**
     * Asserts that the unique constraint on `slug` is enforced at the database level.
     */
    public function test_slug_must_be_unique(): void
    {
        Source::create(['name' => 'USDA FoodData Central', 'slug' => 'usda']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Source::create(['name' => 'USDA Duplicate', 'slug' => 'usda']);
    }
}
