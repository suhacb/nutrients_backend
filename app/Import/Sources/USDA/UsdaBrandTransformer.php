<?php

namespace App\Import\Sources\USDA;

use App\Import\Records\BrandRecord;

class UsdaBrandTransformer {

    public function transform(array $raw): BrandRecord
    {
        return new BrandRecord(
            name:    $raw['brandName'] ?? $raw['brandOwner'],
            owner:   $raw['brandOwner'],
            country: $raw['marketCountry'] ?? null,
        );
    }
}