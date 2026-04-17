<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NutrientTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    protected $casts = [
        'id'         => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function nutrients(): BelongsToMany
    {
        return $this->belongsToMany(Nutrient::class, 'nutrient_nutrient_tag');
    }
}
