<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IngredientCategory extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'created_at',
        'updated_at',
        'deleted_at'
    ];
}
