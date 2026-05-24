<?php

namespace App\Services;

use App\Models\Nutrient;
use App\Models\NutrientSourcePivot;
use Illuminate\Support\Facades\DB;

class NutrientMergeService
{
    public static function merge(Nutrient $imported, Nutrient $canonical): void
    {
        $importedRows = DB::table('ingredient_nutrient')
            ->where('nutrient_id', $imported->id)
            ->get();

        foreach ($importedRows as $row) {
            $existing = DB::table('ingredient_nutrient')
                ->where('ingredient_id', $row->ingredient_id)
                ->where('nutrient_id', $canonical->id)
                ->where('amount_unit_id', $row->amount_unit_id)
                ->first();

            if ($existing) {
                DB::table('ingredient_nutrient')
                    ->where('ingredient_id', $existing->ingredient_id)
                    ->where('nutrient_id', $canonical->id)
                    ->where('amount_unit_id', $existing->amount_unit_id)
                    ->update(['amount' => $existing->amount + $row->amount]);
                DB::table('ingredient_nutrient')
                    ->where('ingredient_id', $row->ingredient_id)
                    ->where('nutrient_id', $imported->id)
                    ->where('amount_unit_id', $row->amount_unit_id)
                    ->delete();
            } else {
                DB::table('ingredient_nutrient')
                    ->where('ingredient_id', $row->ingredient_id)
                    ->where('nutrient_id', $imported->id)
                    ->where('amount_unit_id', $row->amount_unit_id)
                    ->update(['nutrient_id' => $canonical->id]);
            }
        }

        NutrientSourcePivot::where('nutrient_id', $imported->id)
            ->update(['nutrient_id' => $canonical->id]);

        Nutrient::withoutEvents(fn () => $imported->forceDelete());
    }
}
