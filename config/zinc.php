<?php

return [
    'base_url' => env('ZINC_BASE_URI', 'http://localhost'),
    'username' => env('ZINC_USER', null),
    'password' => env('ZINC_PASSWORD', null),

    'indices' => [
        'ingredients' => env('ZINC_INDEX_INGREDIENTS', 'ingredients'),
        'nutrients'   => env('ZINC_INDEX_NUTRIENTS', 'nutrients'),
        'recipes'     => env('ZINC_INDEX_RECIPES', 'recipes'),
    ],

    'index_definitions' => [
        'ingredients' => [
            'storage_type' => 'disk',
            'shards'       => 1,
            'replicas'     => 0,
            'mappings'     => [
                'properties' => [
                    'id'                              => ['type' => 'numeric'],
                    'external_id'                     => ['type' => 'keyword'],
                    'source'                          => ['type' => 'keyword'],
                    'class'                           => ['type' => 'keyword'],
                    'name'                            => ['type' => 'text'],
                    'description'                     => ['type' => 'text'],
                    'slug'                            => ['type' => 'keyword'],
                    'default_amount'                  => ['type' => 'numeric'],
                    'default_amount_unit_id'          => ['type' => 'numeric'],
                    'default_amount_unit.id'          => ['type' => 'numeric'],
                    'default_amount_unit.abbreviation'=> ['type' => 'keyword'],
                    'brand_id'                        => ['type' => 'numeric'],
                    'brand.id'                        => ['type' => 'numeric'],
                    'brand.name'                      => ['type' => 'text'],
                    'brand.owner'                     => ['type' => 'text'],
                    'brand.slug'                      => ['type' => 'keyword'],
                    'brand.country'                   => ['type' => 'keyword'],
                    'created_at'                      => ['type' => 'date'],
                    'updated_at'                      => ['type' => 'date'],
                    'deleted_at'                      => ['type' => 'date', 'index' => false],
                ],
            ],
        ],
        'nutrients' => [
            'storage_type' => 'disk',
            'shards'       => 1,
            'replicas'     => 0,
            'mappings'     => [
                'properties' => [
                    'id'                              => ['type' => 'numeric'],
                    'name'                            => ['type' => 'text'],
                    'description'                     => ['type' => 'text'],
                    'parent_id'                       => ['type' => 'numeric'],
                    'slug'                            => ['type' => 'keyword'],
                    'canonical_unit_id'               => ['type' => 'numeric'],
                    'canonical_unit.id'               => ['type' => 'numeric'],
                    'canonical_unit.abbreviation'     => ['type' => 'keyword'],
                    'parent.id'                       => ['type' => 'numeric'],
                    'parent.name'                     => ['type' => 'text'],
                    'source_mappings.id'              => ['type' => 'numeric'],
                    'source_mappings.external_id'     => ['type' => 'keyword'],
                    'source_mappings.source.id'       => ['type' => 'numeric'],
                    'source_mappings.source.name'     => ['type' => 'text'],
                    'iu_to_canonical_factor'          => ['type' => 'numeric'],
                    'is_label_standard'               => ['type' => 'bool'],
                    'display_order'                   => ['type' => 'numeric'],
                    'created_at'                      => ['type' => 'date'],
                    'updated_at'                      => ['type' => 'date'],
                    'deleted_at'                      => ['type' => 'date', 'index' => false],
                ],
            ],
        ],
        'recipes' => [
            'storage_type' => 'disk',
            'shards'       => 1,
            'replicas'     => 0,
            'mappings'     => [
                'properties' => [
                    'id'                              => ['type' => 'numeric'],
                    'name'                            => ['type' => 'text'],
                    'slug'                            => ['type' => 'keyword'],
                    'description'                     => ['type' => 'text'],
                    'instructions'                    => ['type' => 'text', 'index' => false],
                    'portions'                        => ['type' => 'numeric'],
                    'source_url'                      => ['type' => 'keyword', 'index' => false],
                    'sync_status'                     => ['type' => 'keyword', 'index' => false],

                    // diet_tags (array of objects)
                    'diet_tags.id'                    => ['type' => 'numeric'],
                    'diet_tags.name'                  => ['type' => 'text'],
                    'diet_tags.slug'                  => ['type' => 'keyword'],

                    // ingredients (array of objects — core identity fields only)
                    'ingredients.id'                  => ['type' => 'numeric'],
                    'ingredients.name'                => ['type' => 'text'],
                    'ingredients.slug'                => ['type' => 'keyword'],
                    'ingredients.pivot.amount'        => ['type' => 'numeric'],
                    'ingredients.pivot.unit_id'       => ['type' => 'numeric'],

                    // nutrient_profile (precomputed array of {nutrient_id, amount, unit_id})
                    'nutrient_profile.nutrient_id'    => ['type' => 'numeric'],
                    'nutrient_profile.nutrient_name'  => ['type' => 'text'],
                    'nutrient_profile.amount'         => ['type' => 'numeric'],
                    'nutrient_profile.unit_id'        => ['type' => 'numeric'],
                    'nutrient_profile.unit'           => ['type' => 'keyword'],

                    'created_at'                      => ['type' => 'date'],
                    'updated_at'                      => ['type' => 'date'],
                    'deleted_at'                      => ['type' => 'date', 'index' => false],
                ],
            ],
        ],
    ],
];
