<?php

namespace Tests\Unit\NutrientSourcePivot;

use App\Models\Nutrient;
use App\Models\NutrientSourcePivot;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NutrientSourcePivotModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_fields(): void
    {
        $expected = ['nutrient_id', 'source_id', 'external_id', 'source_name'];

        $this->assertEquals($expected, (new NutrientSourcePivot())->getFillable());
    }

    public function test_belongs_to_nutrient(): void
    {
        $source   = Source::factory()->create();
        $nutrient = Nutrient::factory()->create();
        $pivot    = NutrientSourcePivot::create([
            'nutrient_id' => $nutrient->id,
            'source_id'   => $source->id,
            'external_id' => '1001',
        ]);

        $this->assertTrue($pivot->nutrient->is($nutrient));
    }

    public function test_belongs_to_source(): void
    {
        $source   = Source::factory()->create();
        $nutrient = Nutrient::factory()->create();
        $pivot    = NutrientSourcePivot::create([
            'nutrient_id' => $nutrient->id,
            'source_id'   => $source->id,
            'external_id' => '1001',
        ]);

        $this->assertTrue($pivot->source->is($source));
    }

    public function test_nutrient_has_many_source_mappings(): void
    {
        $source   = Source::factory()->create();
        $nutrient = Nutrient::factory()->create();

        NutrientSourcePivot::create(['nutrient_id' => $nutrient->id, 'source_id' => $source->id, 'external_id' => '1001']);
        NutrientSourcePivot::create(['nutrient_id' => $nutrient->id, 'source_id' => $source->id, 'external_id' => '1002']);

        $this->assertCount(2, $nutrient->sourceMappings);
    }
}
