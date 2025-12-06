<?php

namespace Tests;

use App\Models\Unit;

trait MakesUnit {
    private function makeUnit(int $count = 1): Unit|array
    {
        if ($count === 1) {
            $attributes = Unit::factory()->make()->toArray();
            return Unit::firstOrCreate(
                ['abbreviation' => $attributes['abbreviation']],
                $attributes
            );
        }

        $units = [];

        while (count($units) < $count) {
            $attributes = Unit::factory()->make()->toArray();

            // Use abbreviation as uniqueness key
            $unit = Unit::firstOrCreate(
                ['abbreviation' => $attributes['abbreviation']],
                $attributes
            );

            // Avoid duplicates in the returned array
            if (!in_array($unit->id, array_column($units, 'id'))) {
                $units[] = $unit;
            }
        }

        return $units;
    }
}