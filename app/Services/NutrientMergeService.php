<?php

namespace App\Services;

use App\Models\Nutrient;
use App\Models\SourceNutrient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NutrientMergeService
{
    /**
     * Resolve a source nutrient to an existing canonical: promote its pending
     * ingredient pivots into ingredient_nutrient and mark it resolved.
     */
    public static function merge(SourceNutrient $sourceNutrient, Nutrient $canonical): void
    {
        DB::transaction(function () use ($sourceNutrient, $canonical) {
            self::promoteIngredientPivots($sourceNutrient, $canonical);

            $sourceNutrient->update([
                'nutrient_id' => $canonical->id,
                'resolved_at' => now(),
            ]);
        });
    }

    /**
     * Create a new canonical nutrient from source data, then resolve the source
     * nutrient to it. Optionally assign a parent canonical.
     */
    public static function promote(SourceNutrient $sourceNutrient, ?int $parentId = null): Nutrient
    {
        return DB::transaction(function () use ($sourceNutrient, $parentId) {
            $slug = self::allocateSlug($sourceNutrient->name);

            $canonical = Nutrient::withoutEvents(fn () => Nutrient::create([
                'name'              => $sourceNutrient->name,
                'description'       => $sourceNutrient->description,
                'canonical_unit_id' => $sourceNutrient->canonical_unit_id,
                'parent_id'         => $parentId,
                'slug'              => $slug,
                'is_canonical'      => true,
            ]));

            self::promoteIngredientPivots($sourceNutrient, $canonical);

            $sourceNutrient->update([
                'nutrient_id' => $canonical->id,
                'resolved_at' => now(),
            ]);

            return $canonical;
        });
    }

    /**
     * Move rows from ingredient_source_nutrient into ingredient_nutrient,
     * summing amounts when the canonical already has a row for the same
     * ingredient + unit (avoids unique-constraint violations).
     */
    private static function promoteIngredientPivots(SourceNutrient $sourceNutrient, Nutrient $canonical): void
    {
        $pending = DB::table('ingredient_source_nutrient')
            ->where('source_nutrient_id', $sourceNutrient->id)
            ->get();

        $now = now();

        foreach ($pending as $row) {
            $existing = DB::table('ingredient_nutrient')
                ->where('ingredient_id', $row->ingredient_id)
                ->where('nutrient_id', $canonical->id)
                ->where('amount_unit_id', $row->amount_unit_id)
                ->first();

            if ($existing) {
                DB::table('ingredient_nutrient')
                    ->where('ingredient_id', $row->ingredient_id)
                    ->where('nutrient_id', $canonical->id)
                    ->where('amount_unit_id', $row->amount_unit_id)
                    ->update(['amount' => $existing->amount + $row->amount, 'updated_at' => $now]);
            } else {
                DB::table('ingredient_nutrient')->insert([
                    'ingredient_id'  => $row->ingredient_id,
                    'nutrient_id'    => $canonical->id,
                    'amount'         => $row->amount,
                    'amount_unit_id' => $row->amount_unit_id,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }
        }

        DB::table('ingredient_source_nutrient')
            ->where('source_nutrient_id', $sourceNutrient->id)
            ->delete();
    }

    private static function allocateSlug(string $name): string
    {
        $base    = rtrim(substr(Str::slug($name), 0, 80), '-');
        $slug    = $base;
        $counter = 2;

        while (DB::table('nutrients')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }
}
