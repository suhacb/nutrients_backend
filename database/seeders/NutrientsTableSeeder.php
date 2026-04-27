<?php

namespace Database\Seeders;

use App\Models\Nutrient;
use App\Models\Source;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NutrientsTableSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $system = Source::where('slug', 'system')->firstOrFail();

        // ── Root categories ───────────────────────────────────────────────────────
        $macro   = $this->seed($system, null, 'Macronutrients');
        $micro   = $this->seed($system, null, 'Micronutrients');
        $aminos  = $this->seed($system, null, 'Amino Acids');
        $fatty   = $this->seed($system, null, 'Fatty Acids');

        // ── Macronutrients ────────────────────────────────────────────────────────
        $this->seed($system, $macro, 'Energy');
        $this->seed($system, $macro, 'Protein');
        $this->seed($system, $macro, 'Water');

        $fat   = $this->seed($system, $macro, 'Fat');
        $carbs = $this->seed($system, $macro, 'Carbohydrates');

        // ── Fat sub-categories ────────────────────────────────────────────────────
        $this->seed($system, $fat, 'Saturated Fat');
        $this->seed($system, $fat, 'Trans Fat');
        $this->seed($system, $fat, 'Cholesterol');

        $unsat = $this->seed($system, $fat, 'Unsaturated Fat');
        $this->seed($system, $unsat, 'Monounsaturated Fat');
        $this->seed($system, $unsat, 'Polyunsaturated Fat');

        // ── Carbohydrate sub-categories ───────────────────────────────────────────
        $this->seed($system, $carbs, 'Dietary Fiber');
        $this->seed($system, $carbs, 'Sugars');

        // ── Micronutrients ────────────────────────────────────────────────────────
        $vitams = $this->seed($system, $micro, 'Vitamins');
        $minrls = $this->seed($system, $micro, 'Minerals');

        $this->seed($system, $vitams, 'Fat-soluble Vitamins');
        $this->seed($system, $vitams, 'Water-soluble Vitamins');

        $this->seed($system, $minrls, 'Macrominerals');
        $this->seed($system, $minrls, 'Trace Minerals');

        // ── Fatty Acids ───────────────────────────────────────────────────────────
        $this->seed($system, $fatty, 'Omega-3 Fatty Acids');
        $this->seed($system, $fatty, 'Omega-6 Fatty Acids');
        $this->seed($system, $fatty, 'Omega-9 Fatty Acids');

        // ── Amino Acids ───────────────────────────────────────────────────────────
        $this->seed($system, $aminos, 'Essential Amino Acids');
        $this->seed($system, $aminos, 'Non-essential Amino Acids');
    }

    private function seed(Source $source, ?Nutrient $parent, string $name): Nutrient
    {
        $nutrient = Nutrient::where([
            'source_id'   => $source->id,
            'external_id' => null,
            'name'        => $name,
        ])->first();

        if ($nutrient) {
            if ($nutrient->parent_id === null && $parent !== null) {
                $nutrient->update(['parent_id' => $parent->id]);
            }
            return $nutrient;
        }

        return Nutrient::create([
            'source_id'   => $source->id,
            'external_id' => null,
            'name'        => $name,
            'slug'        => Nutrient::generateUniqueSlug($name, 'nutrients'),
            'parent_id'   => $parent?->id,
        ]);
    }
}
