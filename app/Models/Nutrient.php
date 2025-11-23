<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Nutrient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'source',
        'external_id',
        'name',
        'description',
        'derivation_code',
        'derivation_description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
