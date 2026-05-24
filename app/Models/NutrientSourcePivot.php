<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutrientSourcePivot extends Model
{
    protected $table = 'nutrient_source_mappings';

    protected $fillable = ['nutrient_id', 'source_id', 'external_id', 'source_name'];

    public function nutrient(): BelongsTo
    {
        return $this->belongsTo(Nutrient::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
