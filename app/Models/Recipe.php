<?php

namespace App\Models;

use App\Enums\SyncStatus;
use App\Jobs\SyncRecipeToSearch;
use App\Traits\GeneratesSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Recipe extends Model
{
    use HasFactory, SoftDeletes, GeneratesSlug;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'instructions',
        'portions',
        'source_url',
    ];

    protected $casts = [
        'portions'    => 'integer',
        'sync_status' => SyncStatus::class,
    ];

    protected static function booted(): void
    {
        static::created(function (Recipe $recipe) {
            $recipe->loadForSearch();
            SyncRecipeToSearch::dispatch($recipe, 'insert')->onQueue('recipes');
        });

        static::updated(function (Recipe $recipe) {
            $changed = array_diff(array_keys($recipe->getChanges()), ['sync_status', 'updated_at']);
            if (empty($changed)) {
                return;
            }
            DB::table('recipes')->where('id', $recipe->id)->update(['sync_status' => SyncStatus::Pending->value]);
            $recipe->loadForSearch();
            SyncRecipeToSearch::dispatch($recipe, 'update')->onQueue('recipes');
        });

        static::deleted(function (Recipe $recipe) {
            DB::table('recipes')->where('id', $recipe->id)->update(['sync_status' => SyncStatus::Pending->value]);
            SyncRecipeToSearch::dispatch((object) ['id' => $recipe->id], 'delete')->onQueue('recipes');
        });

        static::restored(function (Recipe $recipe) {
            DB::table('recipes')->where('id', $recipe->id)->update(['sync_status' => SyncStatus::Pending->value]);
            $recipe->loadForSearch();
            SyncRecipeToSearch::dispatch($recipe, 'insert')->onQueue('recipes');
        });
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'recipe_ingredient')
            ->using(RecipeIngredient::class)
            ->withPivot(['amount', 'unit_id'])
            ->withTimestamps();
    }

    public function dietTags(): BelongsToMany
    {
        return $this->belongsToMany(DietTag::class, 'recipe_diet_tag');
    }

    public function loadForSearch(): self
    {
        $this->load(['dietTags', 'ingredients.nutrients']);

        $this->ingredients->each(function ($ingredient) {
            $ingredient->pivot->load('unit');
            $ingredient->nutrients->each(fn($n) => $n->pivot->load('amount_unit'));
        });

        return $this;
    }

    public function computeNutrientProfile(): array
    {
        $this->loadMissing(['ingredients.nutrients']);
        $this->ingredients->each(fn($i) => $i->pivot->loadMissing('unit'));
        $this->ingredients->each(
            fn($i) => $i->nutrients->each(fn($n) => $n->pivot->loadMissing('amount_unit'))
        );

        $totals = [];

        foreach ($this->ingredients as $ingredient) {
            $pivot         = $ingredient->pivot;
            $toBaseFactor  = (float) ($pivot->unit->to_base_factor ?? 1.0);
            $amountInBase  = $pivot->amount * $toBaseFactor;
            $scale         = $amountInBase / 100.0;

            foreach ($ingredient->nutrients as $nutrient) {
                $id = $nutrient->id;

                if (!isset($totals[$id])) {
                    $totals[$id] = [
                        'nutrient_id'   => $id,
                        'nutrient_name' => $nutrient->name,
                        'amount'        => 0.0,
                        'unit_id'       => $nutrient->pivot->amount_unit_id,
                        'unit'          => $nutrient->pivot->amount_unit?->abbreviation,
                    ];
                }

                $totals[$id]['amount'] += $nutrient->pivot->amount * $scale;
            }
        }

        return array_values($totals);
    }

    public function computeNutrientProfilePerPortion(): array
    {
        $portions = max(1, $this->portions);

        return array_map(function ($row) use ($portions) {
            $row['amount'] = round($row['amount'] / $portions, 4);
            return $row;
        }, $this->computeNutrientProfile());
    }
}
