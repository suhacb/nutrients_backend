<?php

namespace Tests\Unit\Import\Pipeline;

use App\Import\Pipeline\BrandLinker;
use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\MakesUnit;
use Tests\TestCase;

class BrandLinkerTest extends TestCase
{
    use RefreshDatabase, MakesUnit;

    protected Source $source;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->source = Source::factory()->create([
            'slug' => 'usda-food-data-central',
            'name' => 'USDA FoodData Central',
        ]);
    }

    private function rawRecord(int $fdcId, string $brandOwner, ?string $brandName = null): array
    {
        return array_filter([
            'fdcId'         => $fdcId,
            'brandOwner'    => $brandOwner,
            'brandName'     => $brandName,
            'marketCountry' => 'United States',
        ], fn($v) => $v !== null);
    }

    private function makeIngredient(int $fdcId): Ingredient
    {
        return Ingredient::factory()->create([
            'external_id' => strval($fdcId),
            'source'      => $this->source->name,
        ]);
    }

    public function test_creates_brand_when_it_does_not_exist(): void
    {
        (new BrandLinker())->process([$this->rawRecord(1106281, "MICHELE'S", "MICHELE'S GRANOLA")], $this->source);

        $this->assertDatabaseHas('brands', ['name' => "MICHELE'S GRANOLA", 'owner' => "MICHELE'S"]);
    }

    public function test_reuses_existing_brand_for_same_slug(): void
    {
        $records = [
            $this->rawRecord(1106281, "MICHELE'S", "MICHELE'S GRANOLA"),
            $this->rawRecord(1106282, "MICHELE'S", "MICHELE'S GRANOLA"),
        ];

        (new BrandLinker())->process($records, $this->source);

        $this->assertDatabaseCount('brands', 1);
    }

    public function test_links_brand_id_to_matching_ingredient_by_fdc_id(): void
    {
        $this->makeIngredient(1106281);

        (new BrandLinker())->process([$this->rawRecord(1106281, "MICHELE'S", "MICHELE'S GRANOLA")], $this->source);

        $ingredient = Ingredient::where('external_id', '1106281')->first();
        $this->assertNotNull($ingredient->brand_id);
        $this->assertEquals("MICHELE'S GRANOLA", Brand::find($ingredient->brand_id)->name);
    }

    public function test_skips_record_with_no_matching_ingredient(): void
    {
        (new BrandLinker())->process([$this->rawRecord(9999999, 'UNKNOWN CORP', 'UNKNOWN')], $this->source);

        $this->assertDatabaseHas('brands', ['name' => 'UNKNOWN']);
        $this->assertDatabaseCount('ingredients', 0);
    }

    public function test_falls_back_to_brand_owner_when_brand_name_absent(): void
    {
        $this->makeIngredient(1106281);

        (new BrandLinker())->process([$this->rawRecord(1106281, 'GENERIC FOODS INC.')], $this->source);

        $ingredient = Ingredient::where('external_id', '1106281')->first();
        $this->assertNotNull($ingredient->brand_id);
        $this->assertEquals('GENERIC FOODS INC.', Brand::find($ingredient->brand_id)->name);
    }

    public function test_is_idempotent(): void
    {
        $this->makeIngredient(1106281);
        $records = [$this->rawRecord(1106281, "MICHELE'S", "MICHELE'S GRANOLA")];

        $linker = new BrandLinker();
        $linker->process($records, $this->source);
        $linker->process($records, $this->source);

        $this->assertDatabaseCount('brands', 1);

        $ingredient = Ingredient::where('external_id', '1106281')->first();
        $this->assertNotNull($ingredient->brand_id);
    }
}
