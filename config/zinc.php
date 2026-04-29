<?php

return [
    'base_url' => env('ZINC_BASE_URI', 'http://localhost'),
    'username' => env('ZINC_USER', null),
    'password' => env('ZINC_PASSWORD', null),

    'indices' => [
        'ingredients' => env('ZINC_INDEX_INGREDIENTS', 'ingredients'),
        'nutrients'   => env('ZINC_INDEX_NUTRIENTS', 'nutrients'),
    ],

    'index_definitions' => [
        'ingredients' => [
            'storage_type' => 'disk',
            'shards'       => 1,
            'replicas'     => 0,
            'fields'       => [
                'id'                     => ['type' => 'integer'],
                'external_id'            => ['type' => 'keyword'],
                'source'                 => ['type' => 'keyword'],
                'class'                  => ['type' => 'keyword'],
                'name'                   => ['type' => 'text'],
                'description'            => ['type' => 'text'],
                'slug'                   => ['type' => 'keyword'],
                'default_amount'         => ['type' => 'numeric'],
                'default_amount_unit_id' => ['type' => 'integer'],
                'brand_id'               => ['type' => 'integer'],
                'brand.id'               => ['type' => 'integer'],
                'brand.name'             => ['type' => 'text'],
                'brand.owner'            => ['type' => 'text'],
                'brand.slug'             => ['type' => 'keyword'],
                'brand.country'          => ['type' => 'keyword'],
                'created_at'             => ['type' => 'date'],
                'updated_at'             => ['type' => 'date'],
                'deleted_at'             => ['type' => 'date', 'index' => false],
            ],
        ],
        'nutrients' => [
            'storage_type' => 'disk',
            'shards'       => 1,
            'replicas'     => 0,
            'fields'       => [
                'id'                     => ['type' => 'integer'],
                'source_id'              => ['type' => 'integer'],
                'external_id'            => ['type' => 'keyword'],
                'name'                   => ['type' => 'text'],
                'description'            => ['type' => 'text'],
                'parent_id'              => ['type' => 'integer'],
                'slug'                   => ['type' => 'keyword'],
                'canonical_unit_id'      => ['type' => 'integer'],
                'iu_to_canonical_factor' => ['type' => 'numeric'],
                'is_label_standard'      => ['type' => 'boolean'],
                'display_order'          => ['type' => 'integer'],
                'created_at'             => ['type' => 'date'],
                'updated_at'             => ['type' => 'date'],
                'deleted_at'             => ['type' => 'date', 'index' => false],
            ],
        ],
    ],
];
