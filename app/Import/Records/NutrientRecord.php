<?php

namespace App\Import\Records;

class NutrientRecord {

    public function __construct(                                                                                          
        public readonly string  $externalId,                                                                              
        public readonly string  $name,
        public readonly ?string $description,                                                                             
        public readonly ?int    $canonicalUnitId,             
    ) {}
}