<?php

namespace App\Models;

use App\Traits\GeneratesSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DietTag extends Model
{
    use HasFactory, GeneratesSlug;

    protected $fillable = ['name', 'slug', 'description'];

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_diet_tag');
    }
}
