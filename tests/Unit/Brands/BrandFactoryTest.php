<?php

namespace Tests\Unit\Brands;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BrandFactoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_factory_creates_brand_with_required_attributes(): void
    {
        $brand = Brand::factory()->create();

        $this->assertNotEmpty($brand->name);
        $this->assertNotEmpty($brand->owner);
        $this->assertNotEmpty($brand->slug);
        $this->assertNotNull(Brand::find($brand->id));
    }

    public function test_factory_slug_is_url_safe(): void
    {
        $brand = Brand::factory()->create();

        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $brand->slug);
    }

    public function test_factory_creates_multiple_brands_with_unique_slugs(): void
    {
        $brands = Brand::factory()->count(5)->create();
        $slugs  = $brands->pluck('slug')->all();

        $this->assertCount(5, array_unique($slugs), 'Factory produced duplicate slugs');
    }

    public function test_factory_accepts_attribute_overrides(): void
    {
        $brand = Brand::factory()->create([
            'name'    => 'KROGER',
            'owner'   => 'The Kroger Co.',
            'country' => 'United States',
        ]);

        $this->assertEquals('KROGER', $brand->name);
        $this->assertEquals('The Kroger Co.', $brand->owner);
        $this->assertEquals('United States', $brand->country);
    }
}
