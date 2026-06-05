<?php

namespace App\Import\Pipeline;

use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Source;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BatchPersistor {

    private array  $categoryMap             = [];
    private array  $brandMap                = [];
    private array  $resolvedNutrientMap     = []; // externalId → canonical nutrient_id (already resolved)
    private array  $sourceNutrientMap       = []; // externalId → source_nutrient_id (all, resolved or not)
    private array  $ingredientMap           = [];
    private array  $newSourceNutrientIds    = [];
    private ?int   $defaultUnitId           = null;
    private ?array $labelNutrientMap        = null;

    public function persist(array $batches, Source $source): void
    {
        DB::transaction(function () use ($batches, $source) {
            $this->upsertCategories($batches);
            $this->upsertBrands($batches);
            $this->upsertSourceNutrients($batches, $source);
            $this->upsertIngredients($batches, $source);
            $this->upsertPivots($batches);
            $this->upsertNutritionFacts($batches);
        });
    }

    public function flushNewSourceNutrientIds(): array
    {
        $ids = $this->newSourceNutrientIds;
        $this->newSourceNutrientIds = [];
        return $ids;
    }

    private function upsertCategories(array $batches): void
    {
        $names = [];
        foreach ($batches as $batch) {
            $names[$batch->category->name] = ['name' => $batch->category->name];
        }

        if (empty($names)) {
            return;
        }

        IngredientCategory::upsert(array_values($names), ['name'], ['name']);

        $this->categoryMap = IngredientCategory::whereIn('name', array_keys($names))
            ->pluck('id', 'name')
            ->all();
    }

    private function upsertBrands(array $batches): void
    {
        $rows = [];
        $now  = now();

        foreach ($batches as $batch) {
            if (!$batch->brand) {
                continue;
            }

            $record = $batch->brand;
            $slug   = Str::slug($record->name);

            if (isset($rows[$slug])) {
                continue;
            }

            $rows[$slug] = [
                'name'       => $record->name,
                'owner'      => $record->owner,
                'slug'       => $slug,
                'country'    => $record->country,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($rows)) {
            return;
        }

        Brand::upsert(
            array_values($rows),
            ['slug'],
            ['name', 'owner', 'country', 'updated_at']
        );

        $this->brandMap = Brand::whereIn('slug', array_keys($rows))
            ->pluck('id', 'slug')
            ->all();
    }

    private function upsertSourceNutrients(array $batches, Source $source): void
    {
        $rows = [];
        $now  = now();

        $allExternalIds = collect($batches)
            ->flatMap(fn($b) => collect($b->nutrients)->pluck('externalId'))
            ->unique()
            ->all();

        // Load existing source_nutrients for this source, joining nutrients to check resolution.
        $existingByExternalId = DB::table('source_nutrients')
            ->where('source_id', $source->id)
            ->whereIn('external_id', $allExternalIds)
            ->get(['id', 'external_id', 'nutrient_id'])
            ->keyBy('external_id');

        foreach ($batches as $batch) {
            foreach ($batch->nutrients as $record) {
                if (isset($rows[$record->externalId])) {
                    continue;
                }

                $rows[$record->externalId] = [
                    'name'              => $record->name,
                    'description'       => $record->description,
                    'canonical_unit_id' => $record->canonicalUnitId,
                ];
            }
        }

        if (empty($rows)) {
            return;
        }

        // Update existing source_nutrients (keep name/description current with source).
        foreach ($existingByExternalId as $externalId => $existing) {
            if (!isset($rows[$externalId])) {
                continue;
            }

            DB::table('source_nutrients')->where('id', $existing->id)->update(array_merge(
                $rows[$externalId],
                ['updated_at' => $now]
            ));

            $this->sourceNutrientMap[$externalId] = $existing->id;

            if ($existing->nutrient_id !== null) {
                $this->resolvedNutrientMap[$externalId] = $existing->nutrient_id;
            }
        }

        // Insert genuinely new source_nutrients.
        foreach ($rows as $externalId => $row) {
            if ($existingByExternalId->has($externalId)) {
                continue;
            }

            $id = DB::table('source_nutrients')->insertGetId(array_merge($row, [
                'source_id'   => $source->id,
                'external_id' => $externalId,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]));

            $this->sourceNutrientMap[$externalId] = $id;
            $this->newSourceNutrientIds[]          = $id;
        }
    }

    private function upsertIngredients(array $batches, Source $source): void
    {
        $rows           = [];
        $usedSlugs      = [];
        $categoryByExId = [];
        $now            = now();

        $allExternalIds = collect($batches)
            ->pluck('ingredient.externalId')
            ->unique()
            ->all();

        $existingSlugs = Ingredient::where('source', $source->name)
            ->whereIn('external_id', $allExternalIds)
            ->pluck('slug', 'external_id')
            ->all();

        foreach ($batches as $batch) {
            $record = $batch->ingredient;

            if (isset($rows[$record->externalId])) {
                continue;
            }

            $slug = $existingSlugs[$record->externalId]
                ?? $this->allocateSlug($record->name, 'ingredients', $usedSlugs);

            $brandSlug = $batch->brand ? Str::slug($batch->brand->name) : null;

            $rows[$record->externalId] = [
                'external_id'            => $record->externalId,
                'source'                 => $source->name,
                'class'                  => $record->class,
                'name'                   => $record->name,
                'description'            => $record->description,
                'default_amount'         => $record->defaultAmount ?? 100,
                'default_amount_unit_id' => $record->defaultAmountUnitId ?? $this->resolveDefaultUnit(),
                'brand_id'               => $brandSlug ? ($this->brandMap[$brandSlug] ?? null) : null,
                'slug'                   => $slug,
                'created_at'             => $now,
                'updated_at'             => $now,
            ];

            if (isset($this->categoryMap[$batch->category->name])) {
                $categoryByExId[$record->externalId] = $this->categoryMap[$batch->category->name];
            }
        }

        if (empty($rows)) {
            return;
        }

        Ingredient::upsert(
            array_values($rows),
            ['source', 'external_id'],
            ['name', 'description', 'class', 'brand_id', 'updated_at']
        );

        $this->ingredientMap = Ingredient::where('source', $source->name)
            ->whereIn('external_id', array_keys($rows))
            ->pluck('id', 'external_id')
            ->all();

        $pivotRows = [];
        foreach ($this->ingredientMap as $externalId => $ingredientId) {
            if (isset($categoryByExId[$externalId])) {
                $pivotRows[] = [
                    'ingredient_id'          => $ingredientId,
                    'ingredient_category_id' => $categoryByExId[$externalId],
                ];
            }
        }

        if (!empty($pivotRows)) {
            DB::table('ingredient_ingredient_category')->insertOrIgnore($pivotRows);
        }
    }

    private function upsertPivots(array $batches): void
    {
        $resolvedRows = [];
        $pendingRows  = [];
        $now          = now();

        foreach ($batches as $batch) {
            foreach ($batch->ingredientNutrients as $record) {
                $ingredientId     = $this->ingredientMap[$record->ingredientExternalId] ?? null;
                $canonicalId      = $this->resolvedNutrientMap[$record->nutrientExternalId] ?? null;
                $sourceNutrientId = $this->sourceNutrientMap[$record->nutrientExternalId] ?? null;

                if (!$ingredientId) {
                    continue;
                }

                if ($canonicalId) {
                    $resolvedRows[] = [
                        'ingredient_id'  => $ingredientId,
                        'nutrient_id'    => $canonicalId,
                        'amount'         => $record->amount,
                        'amount_unit_id' => $record->amountUnitId,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                } elseif ($sourceNutrientId) {
                    $pendingRows[] = [
                        'ingredient_id'    => $ingredientId,
                        'source_nutrient_id' => $sourceNutrientId,
                        'amount'           => $record->amount,
                        'amount_unit_id'   => $record->amountUnitId,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($resolvedRows, 50) as $chunk) {
            DB::table('ingredient_nutrient')->upsert(
                $chunk,
                ['ingredient_id', 'nutrient_id', 'amount_unit_id'],
                ['amount', 'updated_at']
            );
        }

        foreach (array_chunk($pendingRows, 50) as $chunk) {
            DB::table('ingredient_source_nutrient')->upsert(
                $chunk,
                ['ingredient_id', 'source_nutrient_id', 'amount_unit_id'],
                ['amount', 'updated_at']
            );
        }
    }

    private function upsertNutritionFacts(array $batches): void
    {
        $labelMap = $this->loadLabelNutrientMap();
        $rows     = [];
        $now      = now();

        foreach ($batches as $batch) {
            foreach ($batch->nutritionFacts as $record) {
                $ingredientId = $this->ingredientMap[$record->ingredientExternalId] ?? null;

                if (!$ingredientId) {
                    continue;
                }

                $rows[] = [
                    'ingredient_id'  => $ingredientId,
                    'category'       => $record->category,
                    'name'           => $record->name,
                    'amount'         => $record->amount,
                    'amount_unit_id' => $record->amountUnitId,
                    'nutrient_id'    => $labelMap[$record->name] ?? null,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }
        }

        if (empty($rows)) {
            return;
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('ingredient_nutrition_facts')->upsert(
                $chunk,
                ['ingredient_id', 'category', 'name'],
                ['amount', 'amount_unit_id', 'nutrient_id', 'updated_at']
            );
        }
    }

    private function loadLabelNutrientMap(): array
    {
        return $this->labelNutrientMap ??= DB::table('label_nutrient_mappings')
            ->where('status', 'approved')
            ->pluck('nutrient_id', 'label_key')
            ->all();
    }

    private function allocateSlug(string $name, string $table, array &$usedSlugs): string
    {
        $base    = rtrim(substr(Str::slug($name), 0, 80), '-');
        $slug    = $base;
        $counter = 2;

        while (DB::table($table)->where('slug', $slug)->exists()
            || in_array($slug, $usedSlugs)) {
            $slug = $base . '-' . $counter++;
        }

        $usedSlugs[] = $slug;

        return $slug;
    }

    private function resolveDefaultUnit(): int
    {
        return $this->defaultUnitId ??= Unit::where('abbreviation', 'g')->value('id')
            ?? Unit::first()->id;
    }
}
