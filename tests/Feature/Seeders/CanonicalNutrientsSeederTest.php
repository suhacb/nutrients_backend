<?php

namespace Tests\Feature\Seeders;

use App\Models\Nutrient;
use App\Models\SourceNutrient;
use App\Models\Source;
use Database\Seeders\CanonicalNutrientsSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CanonicalNutrientsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_hierarchy_is_correct(): void
    {
        $this->assertParent('Energy',              'Macronutrients');
        $this->assertParent('Vitamin B1',          'Water-soluble Vitamins');
        $this->assertParent('Monounsaturated Fat', 'Unsaturated Fat');
        $this->assertParent('Polyunsaturated Fat', 'Unsaturated Fat');
        $this->assertParent('Iron',                'Trace Minerals');
        $this->assertParent('Glycine',             'Non-essential Amino Acids');
        $this->assertParent('Alpha-Linolenic Acid (ALA)', 'Omega-3 Fatty Acids');
    }

    public function test_canonical_units_are_assigned_correctly(): void
    {
        $this->assertUnit('Energy',   'kcal');
        $this->assertUnit('Protein',  'g');
        $this->assertUnit('Calcium',  'mg');
        $this->assertUnit('Selenium', 'µg');
    }

    public function test_iu_conversion_factors_are_set(): void
    {
        $this->assertEquals(0.3,    $this->canonical('Vitamin A')->iu_to_canonical_factor);
        $this->assertEquals(0.025,  $this->canonical('Vitamin D')->iu_to_canonical_factor);
        $this->assertEquals(0.67,   $this->canonical('Vitamin E')->iu_to_canonical_factor);
        $this->assertNull(          $this->canonical('Vitamin C')->iu_to_canonical_factor);
    }

    public function test_is_label_standard_set_correctly(): void
    {
        $this->assertTrue($this->canonical('Vitamin C')->is_label_standard);
        $this->assertTrue($this->canonical('Vitamin B12')->is_label_standard);
        $this->assertTrue($this->canonical('Iron')->is_label_standard);
        $this->assertFalse($this->canonical('Glycine')->is_label_standard);
        $this->assertFalse($this->canonical('Water')->is_label_standard);
    }

    public function test_display_order_set_correctly(): void
    {
        $this->assertEquals(10,  $this->canonical('Energy')->display_order);
        $this->assertEquals(240, $this->canonical('Vitamin C')->display_order);
        $this->assertEquals(500, $this->canonical('Iron')->display_order);
        $this->assertEquals(780, $this->canonical('Valine')->display_order);
    }

    public function test_updates_category_node_metadata(): void
    {
        $fat  = $this->canonical('Fat');
        $carbs = $this->canonical('Carbohydrates');

        $this->assertTrue($fat->is_label_standard);
        $this->assertEquals(35, $fat->display_order);

        $this->assertTrue($carbs->is_label_standard);
        $this->assertEquals(85, $carbs->display_order);
    }

    public function test_canonical_nutrients_have_no_source_mappings(): void
    {
        $names = ['Energy', 'Vitamin C', 'Calcium', 'Leucine', 'Alpha-Linolenic Acid (ALA)'];

        foreach ($names as $name) {
            $this->assertEquals(
                0,
                $this->canonical($name)->sourceNutrients()->count(),
                "Expected {$name} to have no source mappings"
            );
        }
    }

    public function test_is_idempotent(): void
    {
        $countBefore = Nutrient::whereDoesntHave('sourceNutrients')->count();

        $this->seed(CanonicalNutrientsSeeder::class);

        $this->assertEquals($countBefore, Nutrient::whereDoesntHave('sourceNutrients')->count());
        $this->assertEquals(0.3, $this->canonical('Vitamin A')->iu_to_canonical_factor);
        $this->assertEquals(10,  $this->canonical('Energy')->display_order);
    }

    public function test_does_not_affect_nutrients_with_source_mappings(): void
    {
        $source = Source::factory()->create();

        $usda = Nutrient::factory()->create(['name' => 'Protein']);
        SourceNutrient::create([
            'source_id'   => $source->id,
            'external_id' => '1003',
            'name'        => 'Protein',
            'nutrient_id' => $usda->id,
            'resolved_at' => now(),
        ]);

        $originalParentId = $usda->parent_id;

        $this->seed(CanonicalNutrientsSeeder::class);

        $usda->refresh();
        $this->assertEquals($originalParentId, $usda->parent_id);
        $this->assertNull($usda->canonical_unit_id);
    }

    private function canonical(string $name): Nutrient
    {
        return Nutrient::where('name', $name)
            ->whereDoesntHave('sourceNutrients')
            ->firstOrFail();
    }

    private function assertParent(string $name, string $expectedParent): void
    {
        $nutrient = $this->canonical($name);
        $this->assertNotNull($nutrient->parent_id, "Expected {$name} to have a parent");
        $this->assertEquals(
            $expectedParent,
            $nutrient->parent->name,
            "Expected {$name} parent to be {$expectedParent}"
        );
    }

    private function assertUnit(string $name, string $abbreviation): void
    {
        $nutrient = $this->canonical($name);
        $this->assertNotNull($nutrient->canonical_unit_id, "Expected {$name} to have a canonical unit");
        $this->assertEquals(
            $abbreviation,
            $nutrient->canonicalUnit->abbreviation,
            "Expected {$name} canonical unit to be {$abbreviation}"
        );
    }
}
