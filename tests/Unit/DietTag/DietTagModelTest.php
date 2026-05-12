<?php

namespace Tests\Unit\DietTag;

use App\Models\DietTag;
use App\Models\Recipe;
use App\Traits\GeneratesSlug;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DietTagModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_has_correct_table_and_fillable_fields(): void
    {
        $tag = new DietTag();

        $this->assertEquals('diet_tags', $tag->getTable());
        $this->assertEquals(['name', 'slug', 'description'], $tag->getFillable());
    }

    public function test_it_uses_generates_slug_trait(): void
    {
        $this->assertContains(GeneratesSlug::class, class_uses_recursive(DietTag::class));
    }

    public function test_slug_is_auto_generated_on_create(): void
    {
        $tag = DietTag::create(['name' => 'Low Carb', 'slug' => '']);

        $this->assertEquals('low-carb', $tag->slug);
    }

    public function test_it_can_be_created_with_all_fields(): void
    {
        $tag = DietTag::create([
            'name'        => 'Vegan',
            'slug'        => 'vegan',
            'description' => 'Excludes all animal products.',
        ]);

        $fresh = DietTag::find($tag->id);

        $this->assertEquals('Vegan', $fresh->name);
        $this->assertEquals('vegan', $fresh->slug);
        $this->assertEquals('Excludes all animal products.', $fresh->description);
    }

    public function test_description_is_nullable(): void
    {
        $tag = DietTag::create(['name' => 'Paleo', 'slug' => 'paleo']);

        $this->assertNull(DietTag::find($tag->id)->description);
    }

    public function test_slug_must_be_unique(): void
    {
        DietTag::create(['name' => 'Keto', 'slug' => 'keto']);

        $this->expectException(QueryException::class);

        DietTag::create(['name' => 'Keto Duplicate', 'slug' => 'keto']);
    }

    public function test_recipes_relationship(): void
    {
        Queue::fake();

        $tag    = DietTag::factory()->create();
        $recipe = Recipe::factory()->create();

        $recipe->dietTags()->attach($tag->id);

        $this->assertCount(1, $tag->recipes);
        $this->assertTrue($tag->recipes->first()->is($recipe));
    }
}
