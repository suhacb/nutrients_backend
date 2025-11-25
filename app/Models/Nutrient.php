<?php

namespace App\Models;

use App\Jobs\SyncNutrientToSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Nutrient extends Model
{
    use SoftDeletes, HasFactory;

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

    protected static function booted()
    {
        static::created(function (Nutrient $nutrient) {
            SyncNutrientToSearch::dispatch($nutrient, 'insert')->onQueue('nutrients');
        });

        static::updated(function (Nutrient $nutrient) {
            SyncNutrientToSearch::dispatch($nutrient, 'update')->onQueue('nutrients');
        });

        static::deleted(function (Nutrient $nutrient) {
            SyncNutrientToSearch::dispatch($nutrient, 'delete')->onQueue('nutrients');
        });

        static::restored(function (Nutrient $nutrient) {
            SyncNutrientToSearch::dispatch($nutrient, 'insert')->onQueue('nutrients');
        });
    }
}
