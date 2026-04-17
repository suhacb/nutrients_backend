<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a measurement unit (e.g. gram, kilogram, kcal).
 *
 * Units can be organised into a canonical hierarchy: a base unit (e.g. gram)
 * and any number of derived units that reference it via `base_unit_id`.
 * `to_base_factor` stores the multiplier needed to convert one unit of this
 * type into the base unit (e.g. 1 kg → 1000 g, so `to_base_factor` = 1000).
 *
 * @property int         $id
 * @property string      $name
 * @property string      $abbreviation
 * @property string|null $type           One of: mass, energy, volume, other
 * @property int|null    $base_unit_id
 * @property string|null $to_base_factor Stored as decimal(10); cast preserves precision
 */
class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'abbreviation',
        'type',
        'base_unit_id',
        'to_base_factor',
    ];

    protected $casts = [
        'to_base_factor' => 'decimal:10',
    ];

    /**
     * The canonical base unit that this unit is derived from.
     *
     * Returns null when this unit is itself a base unit (i.e. `base_unit_id` is null).
     */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    /**
     * All units that treat this unit as their canonical base.
     */
    public function derivedUnits(): HasMany
    {
        return $this->hasMany(Unit::class, 'base_unit_id');
    }
}
