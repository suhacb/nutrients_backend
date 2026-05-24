<?php

namespace App\Jobs;

use App\Models\Ingredient;
use App\Models\Nutrient;
use App\Models\Source;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncSourceToSearch implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels;

    public function __construct(public readonly Source $source) {}

    public function handle(): void
    {
        Ingredient::where('source', $this->source->name)
            ->chunk(500, function ($ingredients) {
                $ingredients->each(fn(Ingredient $ingredient) =>
                    SyncIngredientToSearch::dispatch($ingredient, 'update')->onQueue('ingredients')
                );
            });

        Nutrient::whereHas('sourceMappings', fn ($q) => $q->where('source_id', $this->source->id))
            ->chunk(500, function ($nutrients) {
                $nutrients->each(fn(Nutrient $nutrient) =>
                    SyncNutrientToSearch::dispatch($nutrient, 'update')->onQueue('nutrients')
                );
            });
    }
}
