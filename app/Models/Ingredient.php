<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'id',
        'external_id',
        'source',
        'class',
        'name',
        'description',
        'default_amount',
        'default_amount_unit_id',
        'created_at',
        'updated_at',
        'deleted_at'
    ];
}
