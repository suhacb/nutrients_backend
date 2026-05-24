<?php

namespace Database\Seeders;

use App\Models\Nutrient;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NutrientsTableSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ── Root categories ───────────────────────────────────────────────────────
        $macro   = $this->seed(null, 'Macronutrients');
        $micro   = $this->seed(null, 'Micronutrients');
        $aminos  = $this->seed(null, 'Amino Acids');
        $fatty   = $this->seed(null, 'Fatty Acids');

        // ── Macronutrients ────────────────────────────────────────────────────────
        $this->seed($macro, 'Energy');
        $this->seed($macro, 'Protein');
        $this->seed($macro, 'Water');

        $fat   = $this->seed($macro, 'Fat');
        $carbs = $this->seed($macro, 'Carbohydrates');

        // ── Fat sub-categories ────────────────────────────────────────────────────
        $this->seed($fat, 'Saturated Fat');
        $this->seed($fat, 'Trans Fat');
        $this->seed($fat, 'Cholesterol');

        $unsat = $this->seed($fat, 'Unsaturated Fat');
        $this->seed($unsat, 'Monounsaturated Fat');
        $this->seed($unsat, 'Polyunsaturated Fat');

        // ── Carbohydrate sub-categories ───────────────────────────────────────────
        $this->seed($carbs, 'Dietary Fiber');
        $this->seed($carbs, 'Sugars');

        // ── Micronutrients ────────────────────────────────────────────────────────
        $vitams = $this->seed($micro, 'Vitamins');
        $minrls = $this->seed($micro, 'Minerals');

        $this->seed($vitams, 'Fat-soluble Vitamins');
        $this->seed($vitams, 'Water-soluble Vitamins');

        $this->seed($minrls, 'Macrominerals');
        $this->seed($minrls, 'Trace Minerals');

        // ── Fatty Acids ───────────────────────────────────────────────────────────
        $this->seed($fatty, 'Omega-3 Fatty Acids');
        $this->seed($fatty, 'Omega-6 Fatty Acids');
        $this->seed($fatty, 'Omega-9 Fatty Acids');

        // ── Amino Acids ───────────────────────────────────────────────────────────
        $this->seed($aminos, 'Essential Amino Acids');
        $this->seed($aminos, 'Non-essential Amino Acids');
    }

    private function seed(?Nutrient $parent, string $name): Nutrient
    {
        // Canonical hierarchy nutrients have no source mappings.
        $nutrient = Nutrient::where('name', $name)
            ->whereDoesntHave('sourceMappings')
            ->first();

        if ($nutrient) {
            $updates = ['is_canonical' => true];
            if ($nutrient->parent_id === null && $parent !== null) {
                $updates['parent_id'] = $parent->id;
            }
            $nutrient->update($updates);
            return $nutrient;
        }

        return Nutrient::create([
            'name'         => $name,
            'slug'         => Nutrient::generateUniqueSlug($name, 'nutrients'),
            'parent_id'    => $parent?->id,
            'is_canonical' => true,
        ]);
    }
}
