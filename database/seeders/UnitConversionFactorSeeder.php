<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitConversionFactorSeeder extends Seeder
{
    private array $baseUnits = [
        'g'    => 'mass',
        'mL'   => 'volume',
        'kcal' => 'energy',
    ];

    private array $derivedUnits = [
        // Mass (base: g)
        'kg'     => ['base' => 'g', 'factor' => 1000.0],
        'dag'    => ['base' => 'g', 'factor' => 10.0],
        'mg'     => ['base' => 'g', 'factor' => 0.001],
        'µg'     => ['base' => 'g', 'factor' => 0.000001],
        'lb'     => ['base' => 'g', 'factor' => 453.59237],
        'oz'     => ['base' => 'g', 'factor' => 28.34952],
        'st'     => ['base' => 'g', 'factor' => 6350.29318],
        'mg_ATE' => ['base' => 'g', 'factor' => 0.001],

        // Volume (base: mL)
        'L'     => ['base' => 'mL', 'factor' => 1000.0],
        'dL'    => ['base' => 'mL', 'factor' => 100.0],
        'tsp'   => ['base' => 'mL', 'factor' => 4.92892],
        'tbsp'  => ['base' => 'mL', 'factor' => 14.78677],
        'fl oz' => ['base' => 'mL', 'factor' => 29.57353],
        'cup'   => ['base' => 'mL', 'factor' => 236.58824],
        'pt'    => ['base' => 'mL', 'factor' => 473.17647],
        'qt'    => ['base' => 'mL', 'factor' => 946.35295],
        'gal'   => ['base' => 'mL', 'factor' => 3785.41178],

        // Energy (base: kcal)
        'kJ' => ['base' => 'kcal', 'factor' => 0.23901],
        'J'  => ['base' => 'kcal', 'factor' => 0.00023901],
    ];

    public function run(): void
    {
        foreach ($this->baseUnits as $abbreviation => $type) {
            Unit::where('abbreviation', $abbreviation)
                ->update(['to_base_factor' => 1.0, 'base_unit_id' => null]);
        }

        foreach ($this->derivedUnits as $abbreviation => $definition) {
            $base = Unit::where('abbreviation', $definition['base'])->first();

            if (! $base) {
                $this->command?->warn("Base unit '{$definition['base']}' not found — skipping '{$abbreviation}'.");
                continue;
            }

            Unit::where('abbreviation', $abbreviation)
                ->update(['to_base_factor' => $definition['factor'], 'base_unit_id' => $base->id]);
        }
    }
}
