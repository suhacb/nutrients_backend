<?php

namespace Database\Seeders;

use App\Models\Nutrient;
use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CanonicalNutrientsSeeder extends Seeder
{
    use WithoutModelEvents;

    private array $units = [];

    public function run(): void
    {
        $this->units = Unit::whereIn('abbreviation', ['kcal', 'g', 'mg', 'µg'])
            ->get()
            ->keyBy('abbreviation')
            ->all();

        // ── Category node metadata ────────────────────────────────────────────────
        $this->updateCategory('Fat',           true,  35);
        $this->updateCategory('Carbohydrates', true,  85);

        // ── Macronutrients ────────────────────────────────────────────────────────
        $macro = $this->findParent('Macronutrients');
        $this->seed($macro, 'Energy',  'kcal', true,  10);
        $this->seed($macro, 'Protein', 'g',    true,  20);
        $this->seed($macro, 'Water',   'g',    false, 30);

        // ── Fat ───────────────────────────────────────────────────────────────────
        $fat = $this->findParent('Fat');
        $this->seed($fat, 'Saturated Fat', 'g',  true,  40);
        $this->seed($fat, 'Trans Fat',     'g',  true,  50);
        $this->seed($fat, 'Cholesterol',   'mg', true,  60);

        $unsat = $this->findParent('Unsaturated Fat');
        $this->seed($unsat, 'Monounsaturated Fat', 'g', false, 70);
        $this->seed($unsat, 'Polyunsaturated Fat', 'g', false, 80);

        // ── Carbohydrates ─────────────────────────────────────────────────────────
        $carbs = $this->findParent('Carbohydrates');
        $this->seed($carbs, 'Dietary Fiber', 'g', true,  90);
        $this->seed($carbs, 'Sugars',        'g', true,  100);

        // ── Fat-soluble Vitamins ──────────────────────────────────────────────────
        $fatSol = $this->findParent('Fat-soluble Vitamins');
        $this->seed($fatSol, 'Vitamin A', 'µg', true, 200, 0.3);
        $this->seed($fatSol, 'Vitamin D', 'µg', true, 210, 0.025);
        $this->seed($fatSol, 'Vitamin E', 'mg', true, 220, 0.67);
        $vitaminK = $this->seed($fatSol, 'Vitamin K', 'µg', true, 230);
        $this->seed($vitaminK, 'Vitamin K1', 'µg', false, 231);
        $this->seed($vitaminK, 'Vitamin K2', 'µg', false, 232);

        // ── Water-soluble Vitamins ────────────────────────────────────────────────
        $waterSol = $this->findParent('Water-soluble Vitamins');
        $this->seed($waterSol, 'Vitamin C',   'mg', true, 240);
        $this->seed($waterSol, 'Vitamin B1',  'mg', true, 250);
        $this->seed($waterSol, 'Vitamin B2',  'mg', true, 260);
        $this->seed($waterSol, 'Vitamin B3',  'mg', true, 270);
        $this->seed($waterSol, 'Vitamin B5',  'mg', true, 280);
        $this->seed($waterSol, 'Vitamin B6',  'mg', true, 290);
        $this->seed($waterSol, 'Vitamin B7',  'µg', true, 300);
        $this->seed($waterSol, 'Vitamin B9',  'µg', true, 310);
        $this->seed($waterSol, 'Vitamin B12', 'µg', true, 320);

        // ── Macrominerals ─────────────────────────────────────────────────────────
        $macro_min = $this->findParent('Macrominerals');
        $this->seed($macro_min, 'Calcium',    'mg', true,  400);
        $this->seed($macro_min, 'Phosphorus', 'mg', false, 410);
        $this->seed($macro_min, 'Magnesium',  'mg', false, 420);
        $this->seed($macro_min, 'Sodium',     'mg', true,  430);
        $this->seed($macro_min, 'Potassium',  'mg', true,  440);
        $this->seed($macro_min, 'Chloride',   'mg', false, 450);

        // ── Trace Minerals ────────────────────────────────────────────────────────
        $trace = $this->findParent('Trace Minerals');
        $this->seed($trace, 'Iron',       'mg', true,  500);
        $this->seed($trace, 'Zinc',       'mg', false, 510);
        $this->seed($trace, 'Copper',     'mg', false, 520);
        $this->seed($trace, 'Manganese',  'mg', false, 530);
        $this->seed($trace, 'Selenium',   'µg', false, 540);
        $this->seed($trace, 'Iodine',     'µg', false, 550);
        $this->seed($trace, 'Chromium',   'µg', false, 560);
        $this->seed($trace, 'Molybdenum', 'µg', false, 570);
        $this->seed($trace, 'Fluoride',   'mg', false, 580);

        // ── Omega-3 Fatty Acids ───────────────────────────────────────────────────
        $omega3 = $this->findParent('Omega-3 Fatty Acids');
        $this->seed($omega3, 'Alpha-Linolenic Acid (ALA)',  'g', false, 600);
        $this->seed($omega3, 'Eicosapentaenoic Acid (EPA)', 'g', false, 610);
        $this->seed($omega3, 'Docosahexaenoic Acid (DHA)',  'g', false, 620);

        // ── Omega-6 Fatty Acids ───────────────────────────────────────────────────
        $omega6 = $this->findParent('Omega-6 Fatty Acids');
        $this->seed($omega6, 'Linoleic Acid (LA)',         'g', false, 630);
        $this->seed($omega6, 'Arachidonic Acid (AA)',      'g', false, 640);
        $this->seed($omega6, 'Gamma-Linolenic Acid (GLA)', 'g', false, 650);

        // ── Omega-9 Fatty Acids ───────────────────────────────────────────────────
        $omega9 = $this->findParent('Omega-9 Fatty Acids');
        $this->seed($omega9, 'Oleic Acid', 'g', false, 660);

        // ── Essential Amino Acids ─────────────────────────────────────────────────
        $essential = $this->findParent('Essential Amino Acids');
        $this->seed($essential, 'Histidine',     'g', false, 700);
        $this->seed($essential, 'Isoleucine',    'g', false, 710);
        $this->seed($essential, 'Leucine',       'g', false, 720);
        $this->seed($essential, 'Lysine',        'g', false, 730);
        $this->seed($essential, 'Methionine',    'g', false, 740);
        $this->seed($essential, 'Phenylalanine', 'g', false, 750);
        $this->seed($essential, 'Threonine',     'g', false, 760);
        $this->seed($essential, 'Tryptophan',    'g', false, 770);
        $this->seed($essential, 'Valine',        'g', false, 780);

        // ── Non-essential Amino Acids ─────────────────────────────────────────────
        $nonEssential = $this->findParent('Non-essential Amino Acids');
        $this->seed($nonEssential, 'Alanine',       'g', false, 800);
        $this->seed($nonEssential, 'Arginine',      'g', false, 810);
        $this->seed($nonEssential, 'Asparagine',    'g', false, 820);
        $this->seed($nonEssential, 'Aspartic Acid', 'g', false, 830);
        $this->seed($nonEssential, 'Cysteine',      'g', false, 840);
        $this->seed($nonEssential, 'Glutamic Acid', 'g', false, 850);
        $this->seed($nonEssential, 'Glutamine',     'g', false, 860);
        $this->seed($nonEssential, 'Glycine',       'g', false, 870);
        $this->seed($nonEssential, 'Proline',       'g', false, 880);
        $this->seed($nonEssential, 'Serine',        'g', false, 890);
        $this->seed($nonEssential, 'Tyrosine',      'g', false, 900);
    }

    private function seed(
        Nutrient $parent,
        string $name,
        string $unitAbbr,
        bool $isLabelStandard,
        ?int $displayOrder = null,
        ?float $iuFactor = null,
    ): Nutrient {
        $unitId = $this->units[$unitAbbr]->id ?? null;

        $nutrient = Nutrient::where('name', $name)
            ->whereDoesntHave('sourceMappings')
            ->first();

        if ($nutrient) {
            $nutrient->update([
                'parent_id'              => $parent->id,
                'canonical_unit_id'      => $unitId,
                'is_label_standard'      => $isLabelStandard,
                'display_order'          => $displayOrder,
                'iu_to_canonical_factor' => $iuFactor,
                'is_canonical'           => true,
            ]);

            return $nutrient;
        }

        return Nutrient::create([
            'name'                   => $name,
            'parent_id'              => $parent->id,
            'canonical_unit_id'      => $unitId,
            'is_label_standard'      => $isLabelStandard,
            'display_order'          => $displayOrder,
            'iu_to_canonical_factor' => $iuFactor,
            'is_canonical'           => true,
        ]);
    }

    private function updateCategory(string $name, bool $isLabelStandard, ?int $displayOrder): void
    {
        Nutrient::where('name', $name)
            ->whereDoesntHave('sourceMappings')
            ->update([
                'is_label_standard' => $isLabelStandard,
                'display_order'     => $displayOrder,
                'is_canonical'      => true,
            ]);
    }

    private function findParent(string $name): Nutrient
    {
        return Nutrient::where('name', $name)
            ->whereDoesntHave('sourceMappings')
            ->firstOrFail();
    }
}
