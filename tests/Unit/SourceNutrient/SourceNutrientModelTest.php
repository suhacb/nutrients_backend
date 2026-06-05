<?php

namespace Tests\Unit\SourceNutrient;

use App\Models\Ingredient;
use App\Models\Nutrient;
use App\Models\Source;
use App\Models\SourceNutrient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\MakesUnit;
use Tests\TestCase;

class SourceNutrientModelTest extends TestCase
{
    use RefreshDatabase, MakesUnit;

    public function test_fillable_fields(): void
    {
        $expected = ['source_id', 'external_id', 'name', 'description', 'canonical_unit_id', 'nutrient_id', 'resolved_at'];
        $this->assertEquals($expected, (new SourceNutrient())->getFillable());
    }

    public function test_belongs_to_source(): void
    {
        $source = Source::factory()->create();
        $sn     = SourceNutrient::factory()->create(['source_id' => $source->id]);

        $this->assertTrue($sn->source->is($source));
    }

    public function test_belongs_to_nutrient_when_resolved(): void
    {
        $source  = Source::factory()->create();
        $nutrient = Nutrient::withoutEvents(fn () => Nutrient::factory()->create());
        $sn      = SourceNutrient::factory()->resolved($nutrient->id)->create(['source_id' => $source->id]);

        $this->assertTrue($sn->nutrient->is($nutrient));
    }

    public function test_nutrient_has_many_source_nutrients(): void
    {
        $source  = Source::factory()->create();
        $nutrient = Nutrient::withoutEvents(fn () => Nutrient::factory()->create());

        SourceNutrient::factory()->resolved($nutrient->id)->count(2)->create(['source_id' => $source->id]);

        $this->assertCount(2, $nutrient->fresh()->sourceNutrients);
    }

    public function test_is_resolved_returns_true_when_nutrient_id_set(): void
    {
        $source  = Source::factory()->create();
        $nutrient = Nutrient::withoutEvents(fn () => Nutrient::factory()->create());
        $sn      = SourceNutrient::factory()->resolved($nutrient->id)->create(['source_id' => $source->id]);

        $this->assertTrue($sn->isResolved());
    }

    public function test_is_resolved_returns_false_when_nutrient_id_null(): void
    {
        $sn = SourceNutrient::factory()->create();

        $this->assertFalse($sn->isResolved());
    }

    public function test_belongs_to_many_ingredients_via_ingredient_source_nutrient(): void
    {
        $source     = Source::factory()->create();
        $sn         = SourceNutrient::factory()->create(['source_id' => $source->id]);
        $ingredient = Ingredient::factory()->create();
        $unit       = $this->makeUnit();

        DB::table('ingredient_source_nutrient')->insert([
            'ingredient_id'    => $ingredient->id,
            'source_nutrient_id' => $sn->id,
            'amount'           => 5.0,
            'amount_unit_id'   => $unit->id,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->assertCount(1, $sn->fresh()->ingredients);
        $this->assertTrue($sn->fresh()->ingredients->first()->is($ingredient));
    }
}
