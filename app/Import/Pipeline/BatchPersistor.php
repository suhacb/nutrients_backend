<?php

namespace App\Import\Pipeline;

use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Nutrient;
use App\Models\Source;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BatchPersistor {

    private array $categoryMap      = [];
    private array $brandMap         = [];
    private array $nutrientMap      = [];
    private array $ingredientMap    = [];
    private array $newNutrientIds   = [];
    private ?int  $defaultUnitId    = null;

    public function persist(array $batches, Source $source): void
    {
        DB::transaction(function () use ($batches, $source) {
            $this->upsertCategories($batches);
            $this->upsertBrands($batches);
            $this->upsertNutrients($batches, $source);
            $this->upsertIngredients($batches, $source);
            $this->upsertPivots($batches);
            $this->upsertNutritionFacts($batches);
        });
    }

    public function flushNewNutrientIds(): array
    {
        $ids = $this->newNutrientIds;
        $this->newNutrientIds = [];
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

    private function upsertNutrients(array $batches, Source $source): void
    {
        $rows      = [];
        $usedSlugs = [];
        $now       = now();

        $allExternalIds = collect($batches)
            ->flatMap(fn($b) => collect($b->nutrients)->pluck('externalId'))
            ->unique()
            ->all();

        // Look up existing nutrients via source mappings.
        // All USDA source files share the same Source record, so (source_id, external_id)
        // uniquely identifies whether a nutrient from this provider was already imported
        // — including nutrients that were later merged into a canonical, since
        // NutrientMergeService re-points the source mapping to the canonical before
        // deleting the duplicate.
        // Soft-deleted nutrients are included so they can be restored; the source data
        // still contains them, so restoring is the right semantic over creating a duplicate.
        $existingByExternalId = DB::table('nutrient_source_mappings')
            ->where('nutrient_source_mappings.source_id', $source->id)
            ->whereIn('nutrient_source_mappings.external_id', $allExternalIds)
            ->join('nutrients', 'nutrients.id', '=', 'nutrient_source_mappings.nutrient_id')
            ->select('nutrient_source_mappings.external_id', 'nutrient_source_mappings.nutrient_id', 'nutrients.slug', 'nutrients.deleted_at')
            ->get()
            ->keyBy('external_id');

        foreach ($batches as $batch) {
            foreach ($batch->nutrients as $record) {
                if (isset($rows[$record->externalId])) {
                    continue;
                }

                $existing = $existingByExternalId->get($record->externalId);
                $slug = $existing?->slug
                    ?? $this->allocateSlug($record->name, 'nutrients', $usedSlugs);

                $rows[$record->externalId] = [
                    'name'              => $record->name,
                    'description'       => $record->description,
                    'canonical_unit_id' => $record->canonicalUnitId,
                    'slug'              => $slug,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }
        }

        if (empty($rows)) {
            return;
        }

        // Update existing nutrients (covers re-imports, merged canonicals, and restores
        // soft-deleted nutrients — the source file still contains them so restoring is
        // the correct intent rather than creating a duplicate with a broken mapping)
        foreach ($existingByExternalId as $externalId => $existing) {
            if (!isset($rows[$externalId])) {
                continue;
            }
            $row = $rows[$externalId];
            DB::table('nutrients')->where('id', $existing->nutrient_id)->update([
                'name'              => $row['name'],
                'description'       => $row['description'],
                'canonical_unit_id' => $row['canonical_unit_id'],
                'deleted_at'        => null,
                'updated_at'        => $row['updated_at'],
            ]);
            $this->nutrientMap[$externalId] = $existing->nutrient_id;
        }

        // Insert genuinely new nutrients and their source mappings
        $mappingRows = [];
        foreach ($rows as $externalId => $row) {
            if ($existingByExternalId->has($externalId)) {
                continue;
            }
            $nutrientId = DB::table('nutrients')->insertGetId($row);
            $this->nutrientMap[$externalId]  = $nutrientId;
            $this->newNutrientIds[]          = $nutrientId;
            $mappingRows[] = [
                'nutrient_id' => $nutrientId,
                'source_id'   => $source->id,
                'external_id' => $externalId,
                'source_name' => $row['name'],
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        if (!empty($mappingRows)) {
            DB::table('nutrient_source_mappings')->insertOrIgnore($mappingRows);
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
                'external_id'           => $record->externalId,
                'source'                => $source->name,
                'class'                 => $record->class,
                'name'                  => $record->name,
                'description'           => $record->description,
                'default_amount'        => $record->defaultAmount ?? 100,
                'default_amount_unit_id'=> $record->defaultAmountUnitId ?? $this->resolveDefaultUnit(),
                'brand_id'              => $brandSlug ? ($this->brandMap[$brandSlug] ?? null) : null,
                'slug'                  => $slug,
                'created_at'            => $now,
                'updated_at'            => $now,
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
            DB::table('ingredient_ingredient_category')
                ->insertOrIgnore($pivotRows);
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
            DB::table('ingredient_nutrient')->upsert(
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
            DB::table('ingredient_nutrition_facts')->upsert(
                $chunk,
                ['ingredient_id', 'category', 'name'],
                ['amount', 'amount_unit_id', 'updated_at']
            );
        }
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