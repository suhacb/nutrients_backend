<?php

namespace App\Import\Pipeline;

class BatchPersistor {

    private array $categoryMap    = [];
    private array $nutrientMap    = [];
    private array $ingredientMap  = [];

    public function persist(array $batches, \App\Models\Source $source): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($batches, $source) {
            $this->upsertCategories($batches);
            $this->upsertNutrients($batches, $source);
            $this->upsertIngredients($batches, $source);
            $this->upsertPivots($batches);
            $this->upsertNutritionFacts($batches);
        });
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

        \App\Models\IngredientCategory::upsert(array_values($names), ['name'], ['name']);

        $this->categoryMap = \App\Models\IngredientCategory::whereIn('name', array_keys($names))
            ->pluck('id', 'name')
            ->all();
    }

    private function upsertNutrients(array $batches, \App\Models\Source $source): void
    {
        $rows      = [];
        $usedSlugs = [];
        $now       = now();

        $allExternalIds = collect($batches)
            ->flatMap(fn($b) => collect($b->nutrients)->pluck('externalId'))
            ->unique()
            ->all();

        $existingSlugs = \App\Models\Nutrient::where('source_id', $source->id)
            ->whereIn('external_id', $allExternalIds)
            ->pluck('slug', 'external_id')
            ->all();

        foreach ($batches as $batch) {
            foreach ($batch->nutrients as $record) {
                if (isset($rows[$record->externalId])) {
                    continue;
                }

                $slug = $existingSlugs[$record->externalId]
                    ?? $this->allocateSlug($record->name, 'nutrients', $usedSlugs);

                $rows[$record->externalId] = [
                    'source_id'        => $source->id,
                    'external_id'      => $record->externalId,
                    'name'             => $record->name,
                    'description'      => $record->description,
                    'canonical_unit_id'=> $record->canonicalUnitId,
                    'slug'             => $slug,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }
        }

        if (empty($rows)) {
            return;
        }

        \App\Models\Nutrient::upsert(
            array_values($rows),
            ['source_id', 'external_id'],
            ['name', 'description', 'canonical_unit_id', 'updated_at']
        );

        $this->nutrientMap = \App\Models\Nutrient::where('source_id', $source->id)
            ->whereIn('external_id', array_keys($rows))
            ->pluck('id', 'external_id')
            ->all();
    }

    private function upsertIngredients(array $batches, \App\Models\Source $source): void
    {
        $rows      = [];
        $usedSlugs = [];
        $now       = now();

        $allExternalIds = collect($batches)
            ->pluck('ingredient.externalId')
            ->unique()
            ->all();

        $existingSlugs = \App\Models\Ingredient::where('source', $source->name)
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

            $rows[$record->externalId] = [
                'external_id'           => $record->externalId,
                'source'                => $source->name,
                'class'                 => $record->class,
                'name'                  => $record->name,
                'description'           => $record->description,
                'default_amount'        => $record->defaultAmount ?? 100,
                'default_amount_unit_id'=> $record->defaultAmountUnitId ?? $this->resolveDefaultUnit(),
                'slug'                  => $slug,
                'created_at'            => $now,
                'updated_at'            => $now,
            ];
        }

        if (empty($rows)) {
            return;
        }

        \App\Models\Ingredient::upsert(
            array_values($rows),
            ['source', 'external_id'],
            ['name', 'description', 'class', 'updated_at']
        );

        $ingredients = \App\Models\Ingredient::where('source', $source->name)
            ->whereIn('external_id', array_keys($rows))
            ->get();

        foreach ($ingredients as $ingredient) {
            $this->ingredientMap[$ingredient->external_id] = $ingredient->id;

            $categoryName = null;
            foreach ($batches as $batch) {
                if ($batch->ingredient->externalId === $ingredient->external_id) {
                    $categoryName = $batch->category->name;
                    break;
                }
            }

            if ($categoryName && isset($this->categoryMap[$categoryName])) {
                $ingredient->categories()->syncWithoutDetaching([$this->categoryMap[$categoryName]]);
            }
        }
    }

    private function upsertPivots(array $batches): void
    {
        $rows = [];
        $now  = now();

        foreach ($batches as $batch) {
            foreach ($batch->ingredientNutrients as $record) {
                $ingredientId = $this->ingredientMap[$record->ingredientExternalId] ?? null;
                $nutrientId   = $this->nutrientMap[$record->nutrientExternalId] ?? null;

                if (!$ingredientId || !$nutrientId) {
                    continue;
                }

                $rows[] = [
                    'ingredient_id'  => $ingredientId,
                    'nutrient_id'    => $nutrientId,
                    'amount'         => $record->amount,
                    'amount_unit_id' => $record->amountUnitId,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }
        }

        if (empty($rows)) {
            return;
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            \Illuminate\Support\Facades\DB::table('ingredient_nutrient')->upsert(
                $chunk,
                ['ingredient_id', 'nutrient_id', 'amount_unit_id'],
                ['amount', 'updated_at']
            );
        }
    }

    private function upsertNutritionFacts(array $batches): void
    {
        $rows = [];
        $now  = now();

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
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }
        }

        if (empty($rows)) {
            return;
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            \Illuminate\Support\Facades\DB::table('ingredient_nutrition_facts')->upsert(
                $chunk,
                ['ingredient_id', 'category', 'name'],
                ['amount', 'amount_unit_id', 'updated_at']
            );
        }
    }

    private function allocateSlug(string $name, string $table, array &$usedSlugs): string
    {
        $base    = rtrim(substr(\Illuminate\Support\Str::slug($name), 0, 80), '-');
        $slug    = $base;
        $counter = 2;

        while (\Illuminate\Support\Facades\DB::table($table)->where('slug', $slug)->exists()
            || in_array($slug, $usedSlugs)) {
            $slug = $base . '-' . $counter++;
        }

        $usedSlugs[] = $slug;

        return $slug;
    }

    private function resolveDefaultUnit(): int
    {
        return \App\Models\Unit::where('abbreviation', 'g')->value('id')
            ?? \App\Models\Unit::first()->id;
    }
}