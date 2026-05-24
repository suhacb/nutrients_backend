<?php

namespace App\Console\Commands;

use App\Models\IngredientNutrientPivot;
use App\Models\Nutrient;
use App\Models\NutrientMappingReview;
use App\Models\NutrientSourcePivot;
use Illuminate\Console\Command;

class ReviewNutrientMappings extends Command
{
    protected $signature = 'nutrients:review-mappings';

    protected $description = 'Interactively review pending AI deduplication decisions for imported nutrients';

    public function handle(): int
    {
        $reviews = NutrientMappingReview::where('status', 'pending')
            ->with(['nutrient', 'suggestedCanonical'])
            ->get();

        if ($reviews->isEmpty()) {
            $this->info('No pending mapping reviews.');
            return self::SUCCESS;
        }

        foreach ($reviews as $review) {
            $this->newLine();
            $this->line("Imported:   <comment>{$review->nutrient->name}</comment>");
            $this->line("Suggested:  <info>{$review->suggestedCanonical->name}</info>");
            $this->line("Confidence: {$review->confidence}%");
            $this->line("Reasoning:  {$review->reasoning}");

            $action = $this->ask('Action? [m]erge / [k]eep / [s]kip');

            match ($action) {
                'm' => $this->applyMerge($review),
                'k' => $this->applyKeep($review),
                default => null,
            };
        }

        return self::SUCCESS;
    }

    private function applyMerge(NutrientMappingReview $review): void
    {
        $imported  = $review->nutrient;
        $canonical = $review->suggestedCanonical;

        IngredientNutrientPivot::where('nutrient_id', $imported->id)
            ->update(['nutrient_id' => $canonical->id]);

        NutrientSourcePivot::where('nutrient_id', $imported->id)
            ->update(['nutrient_id' => $canonical->id]);

        Nutrient::withoutEvents(fn () => $imported->forceDelete());

        $review->update([
            'status'      => 'approved',
            'resolved_at' => now(),
        ]);

        $this->info("Merged \"{$imported->name}\" -> \"{$canonical->name}\".");
    }

    private function applyKeep(NutrientMappingReview $review): void
    {
        $review->update([
            'decision_type' => 'keep',
            'status'        => 'approved',
            'resolved_at'   => now(),
        ]);

        $this->info("Kept \"{$review->nutrient->name}\" as a distinct nutrient.");
    }
}
