<?php

namespace Tests\Unit\Brands;

use App\Exceptions\BrandHasIngredientsException;
use App\Models\Brand;
use App\Models\Ingredient;
use App\Traits\GeneratesSlug;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\MakesUnit;
use Tests\TestCase;

class BrandModelTest extends TestCase
{
    use RefreshDatabase, MakesUnit;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_it_has_correct_table_and_fillable_fields(): void
    {
        $brand = new Brand();

        $this->assertEquals('brands', $brand->getTable());
        $this->assertEquals(
            ['name', 'owner', 'slug', 'country', 'description'],
            $brand->getFillable()
        );
    }

    public function test_it_uses_generates_slug_trait(): void
    {
        $this->assertContains(GeneratesSlug::class, class_uses_recursive(Brand::class));
    }

    public function test_it_uses_soft_deletes_trait(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses_recursive(Brand::class));
    }

    public function test_it_can_be_created_with_all_fields(): void
    {
        $brand = Brand::create([
            'name'        => 'KROGER',
            'owner'       => 'The Kroger Co.',
            'slug'        => 'kroger',
            'country'     => 'United States',
            'description' => 'An American retail company.',
        ]);

        $fresh = Brand::find($brand->id);

        $this->assertEquals('KROGER', $fresh->name);
        $this->assertEquals('The Kroger Co.', $fresh->owner);
        $this->assertEquals('kroger', $fresh->slug);
        $this->assertEquals('United States', $fresh->country);
        $this->assertEquals('An American retail company.', $fresh->description);
    }

    public function test_country_is_nullable(): void
    {
        $brand = Brand::create(['name' => 'ACME', 'owner' => 'ACME Corp', 'slug' => 'acme']);

        $this->assertNull(Brand::find($brand->id)->country);
    }

    public function test_description_is_nullable(): void
    {
        $brand = Brand::create(['name' => 'ACME', 'owner' => 'ACME Corp', 'slug' => 'acme']);

        $this->assertNull(Brand::find($brand->id)->description);
    }

    public function test_ingredients_relationship_returns_associated_ingredients(): void
    {
        $brand      = Brand::factory()->create();
        $ingredient = Ingredient::factory()->create(['brand_id' => $brand->id]);

        $this->assertTrue($brand->ingredients->contains($ingredient));
        $this->assertCount(1, $brand->ingredients);
    }

    public function test_force_delete_throws_when_brand_has_ingredients(): void
    {
        $brand = Brand::factory()->create();
        Ingredient::factory()->create(['brand_id' => $brand->id]);

        $this->expectException(BrandHasIngredientsException::class);

        $brand->forceDelete();
    }

    public function test_soft_delete_succeeds_when_brand_has_ingredients(): void
    {
        $brand = Brand::factory()->create();
        Ingredient::factory()->create(['brand_id' => $brand->id]);

        $brand->delete();

        $this->assertSoftDeleted($brand);
    }
}
