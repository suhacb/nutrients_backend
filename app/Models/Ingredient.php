<?php

namespace App\Models;

use App\Jobs\SyncIngredientToSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Model
{
    use HasFactory, SoftDeletes;
    
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

    protected static function booted()
    {
        static::created(function (Ingredient $ingredient) {
            SyncIngredientToSearch::dispatch($ingredient, 'insert')->onQueue('ingredients');
        });

        static::updated(function (Ingredient $ingredient) {
            SyncIngredientToSearch::dispatch($ingredient, 'update')->onQueue('ingredients');
        });

        static::deleted(function (Ingredient $ingredient) {
            SyncIngredientToSearch::dispatch($ingredient, 'delete')->onQueue('ingredients');
        });

        static::restored(function (Ingredient $ingredient) {
            SyncIngredientToSearch::dispatch($ingredient, 'insert')->onQueue('ingredients');
        });
    }
}
