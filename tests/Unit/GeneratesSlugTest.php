<?php

namespace Tests\Unit;

use App\Models\Nutrient;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GeneratesSlugTest extends TestCase
{
    use RefreshDatabase;

    protected Source $source;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->source = Source::factory()->create();
    }

    // -------------------------------------------------------------------------
    // Auto-generation on creating
    // -------------------------------------------------------------------------

    public function test_generates_kebab_case_slug_from_name(): void
    {
        $nutrient = Nutrient::factory()->create([
            'source_id' => $this->source->id,
            'name'      => 'Vitamin C',
        ]);

        $this->assertEquals('vitamin-c', $nutrient->slug);
    }

    public function test_slug_strips_special_characters(): void
    {
        $nutrient = Nutrient::factory()->create([
            'source_id' => $this->source->id,
            'name'      => 'Vitamin B12 (Cobalamin)',
        ]);

        $this->assertEquals('vitamin-b12-cobalamin', $nutrient->slug);
    }

    public function test_slug_is_truncated_to_80_characters(): void
    {
        $nutrient = Nutrient::factory()->create([
            'source_id' => $this->source->id,
            'name'      => str_repeat('abcde ', 20),
        ]);

        $this->assertLessThanOrEqual(80, strlen($nutrient->slug));
    }

    public function test_truncated_slug_has_no_trailing_hyphen(): void
    {
        $nutrient = Nutrient::factory()->create([
            'source_id' => $this->source->id,
            'name'      => str_repeat('abcde ', 20),
        ]);

        $this->assertStringEndsNotWith('-', $nutrient->slug);
    }

    public function test_appends_2_when_slug_already_taken(): void
    {
        Nutrient::factory()->create([
            'source_id' => $this->source->id,
            'name'      => 'Vitamin C',
        ]);

        $second = Nutrient::factory()->create([
            'source_id' => $this->source->id,
            'name'      => 'Vitamin C',
        ]);

        $this->assertEquals('vitamin-c-2', $second->slug);
    }

    public function test_increments_suffix_until_free_slot_found(): void
    {
        Nutrient::factory()->create(['source_id' => $this->source->id, 'name' => 'Vitamin C']);
        Nutrient::factory()->create(['source_id' => $this->source->id, 'name' => 'Vitamin C']);

        $third = Nutrient::factory()->create([
            'source_id' => $this->source->id,
            'name'      => 'Vitamin C',
        ]);

        $this->assertEquals('vitamin-c-3', $third->slug);
    }

    public function test_does_not_overwrite_explicitly_set_slug(): void
    {
        $nutrient = Nutrient::factory()->create([
            'source_id' => $this->source->id,
            'name'      => 'Vitamin C',
            'slug'      => 'my-custom-slug',
        ]);

        $this->assertEquals('my-custom-slug', $nutrient->slug);
    }

    // -------------------------------------------------------------------------
    // Static generateUniqueSlug
    // -------------------------------------------------------------------------

    public function test_generate_unique_slug_returns_base_slug_when_table_is_empty(): void
    {
        $slug = Nutrient::generateUniqueSlug('Vitamin C', 'nutrients');

        $this->assertEquals('vitamin-c', $slug);
    }

    public function test_generate_unique_slug_increments_when_slug_taken(): void
    {
        Nutrient::factory()->create(['source_id' => $this->source->id, 'name' => 'Vitamin C']);

        $slug = Nutrient::generateUniqueSlug('Vitamin C', 'nutrients');

        $this->assertEquals('vitamin-c-2', $slug);
    }

    public function test_generate_unique_slug_excludes_own_record_by_id(): void
    {
        $nutrient = Nutrient::factory()->create([
            'source_id' => $this->source->id,
            'name'      => 'Vitamin C',
        ]);

        $slug = Nutrient::generateUniqueSlug('Vitamin C', 'nutrients', $nutrient->id);

        $this->assertEquals('vitamin-c', $slug);
    }
}
