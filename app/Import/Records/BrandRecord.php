<?php

namespace App\Import\Records;

class BrandRecord {
    public function __construct(
        public readonly string  $name,
        public readonly string  $owner,
        public readonly ?string $country,
    ) {}
}