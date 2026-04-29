<?php

namespace App\Import\Pipeline;

use App\Models\Brand;
use App\Models\Source;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BrandLinker {

    public function process(array $rawRecords, Source $source): void
    {
        $brandRows    = [];
        $linkMap      = []; // slug → [fdcId, ...]
        $now          = now();

        foreach ($rawRecords as $raw) {
            $name = $raw['brandName'] ?? $raw['brandOwner'];
            $slug = Str::slug($name);

            if (!isset($brandRows[$slug])) {
                $brandRows[$slug] = [
                    'name'       => $name,
                    'owner'      => $raw['brandOwner'],
                    'slug'       => $slug,
                    'country'    => $raw['marketCountry'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $linkMap[$slug][] = strval($raw['fdcId']);
        }

        if (empty($brandRows)) {
            return;
        }

        Brand::upsert(
            array_values($brandRows),
            ['slug'],
            ['name', 'owner', 'country', 'updated_at']
        );

        $brandIdMap = Brand::whereIn('slug', array_keys($brandRows))
            ->pluck('id', 'slug')
            ->all();

        foreach ($linkMap as $slug => $fdcIds) {
            $brandId = $brandIdMap[$slug] ?? null;

            if (!$brandId) {
                continue;
            }

            DB::table('ingredients')
                ->whereIn('external_id', $fdcIds)
                ->where('source', $source->name)
                ->update(['brand_id' => $brandId]);
        }
    }
}
