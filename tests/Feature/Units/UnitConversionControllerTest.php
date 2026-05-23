<?php

namespace Tests\Feature\Units;

use App\Models\Nutrient;
use App\Models\Unit;
use Database\Seeders\UnitConversionFactorSeeder;
use Database\Seeders\UnitsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\LoginTestUser;
use Tests\TestCase;

class UnitConversionControllerTest extends TestCase
{
    use RefreshDatabase, LoginTestUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->login();
        Queue::fake();
        $this->seed([UnitsTableSeeder::class, UnitConversionFactorSeeder::class]);
    }

    public function test_converts_mass_units(): void
    {
        $gram  = Unit::where('abbreviation', 'g')->first();
        $ounce = Unit::where('abbreviation', 'oz')->first();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('units.convert'), [
                'value'       => 100,
                'from_unit_id' => $gram->id,
                'to_unit_id'   => $ounce->id,
            ])
            ->assertStatus(200)
            ->assertJsonStructure(['value', 'from_unit', 'to_unit'])
            ->assertJsonPath('from_unit.abbreviation', 'g')
            ->assertJsonPath('to_unit.abbreviation', 'oz')
            ->assertJsonFragment(['value' => 3.5274]);
    }

    public function test_converts_volume_units(): void
    {
        $liter = Unit::where('abbreviation', 'L')->first();
        $cup   = Unit::where('abbreviation', 'cup')->first();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('units.convert'), [
                'value'        => 1,
                'from_unit_id' => $liter->id,
                'to_unit_id'   => $cup->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('from_unit.abbreviation', 'L')
            ->assertJsonPath('to_unit.abbreviation', 'cup')
            ->assertJsonFragment(['value' => 4.2268]);
    }

    public function test_converts_energy_units(): void
    {
        $kcal = Unit::where('abbreviation', 'kcal')->first();
        $kj   = Unit::where('abbreviation', 'kJ')->first();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('units.convert'), [
                'value'        => 1,
                'from_unit_id' => $kcal->id,
                'to_unit_id'   => $kj->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('from_unit.abbreviation', 'kcal')
            ->assertJsonPath('to_unit.abbreviation', 'kJ')
            ->assertJsonFragment(['value' => 4.1839]);
    }

    public function test_converts_iu_for_nutrient(): void
    {
        $iu        = Unit::where('abbreviation', 'IU')->first();
        $microgram = Unit::where('abbreviation', 'µg')->first();

        $nutrient = Nutrient::factory()->create([
            'iu_to_canonical_factor' => 0.025,
            'canonical_unit_id'      => $microgram->id,
        ]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('units.convert'), [
                'value'        => 400,
                'from_unit_id' => $iu->id,
                'nutrient_id'  => $nutrient->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('from_unit.abbreviation', 'IU')
            ->assertJsonPath('to_unit.abbreviation', 'µg')
            ->assertJsonPath('nutrient_id', $nutrient->id)
            ->assertJsonFragment(['value' => 10.0]);
    }

    public function test_returns_422_for_incompatible_units(): void
    {
        $gram       = Unit::where('abbreviation', 'g')->first();
        $milliliter = Unit::where('abbreviation', 'mL')->first();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('units.convert'), [
                'value'        => 100,
                'from_unit_id' => $gram->id,
                'to_unit_id'   => $milliliter->id,
            ])
            ->assertStatus(422);
    }

    public function test_returns_422_when_to_unit_id_omitted_for_non_iu(): void
    {
        $gram = Unit::where('abbreviation', 'g')->first();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('units.convert'), [
                'value'        => 100,
                'from_unit_id' => $gram->id,
            ])
            ->assertStatus(422);
    }

    public function test_returns_422_when_nutrient_id_omitted_for_iu(): void
    {
        $iu  = Unit::where('abbreviation', 'IU')->first();
        $mug = Unit::where('abbreviation', 'µg')->first();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('units.convert'), [
                'value'        => 400,
                'from_unit_id' => $iu->id,
                'to_unit_id'   => $mug->id,
            ])
            ->assertStatus(422);
    }

    public function test_returns_422_when_nutrient_has_no_iu_factor(): void
    {
        $iu       = Unit::where('abbreviation', 'IU')->first();
        $nutrient = Nutrient::factory()->create(['iu_to_canonical_factor' => null]);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('units.convert'), [
                'value'        => 400,
                'from_unit_id' => $iu->id,
                'nutrient_id'  => $nutrient->id,
            ])
            ->assertStatus(422);
    }

    public function test_returns_422_for_unknown_unit_ids(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('units.convert'), [
                'value'        => 100,
                'from_unit_id' => 99999,
                'to_unit_id'   => 99998,
            ])
            ->assertStatus(422);
    }
}
