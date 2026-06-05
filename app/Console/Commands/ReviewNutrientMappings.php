<?php

namespace App\Console\Commands;

use App\Models\NutrientMappingReview;
use Illuminate\Console\Command;

class ReviewNutrientMappings extends Command
{
    protected $signature = 'nutrients:review-mappings';

    protected $description = 'Interactively review pending AI deduplication decisions for imported nutrients';

    public function handle(): int
    {
        $reviews = NutrientMappingReview::where('status', 'pending')
            ->with(['sourceNutrient', 'suggestedCanonical'])
            ->get();

        if ($reviews->isEmpty()) {
            $this->info('No pending mapping reviews.');
            return self::SUCCESS;
        }

        foreach ($reviews as $review) {
            $this->newLine();
            $suggested = $review->suggestedCanonical?->name ?? 'none';
            $this->line("Imported:   <comment>{$review->sourceNutrient->name}</comment>");
            $this->line("Suggested:  <info>{$suggested}</info>");
            $this->line("Type:       {$review->decision_type}");
            $this->line("Confidence: {$review->confidence}%");
            $this->line("Reasoning:  {$review->reasoning}");

            $action = $this->ask('Action? [m]erge / [p]arent / [k]eep / [s]kip');

            match ($action) {
                'm' => $this->applyMerge($review),
                'p' => $this->applyParent($review),
                'k' => $this->applyKeep($review),
                default => null,
            };
        }

        return self::SUCCESS;
    }

    private function applyMerge(NutrientMappingReview $review): void
    {
        $importedName  = $review->sourceNutrient->name;
        $canonicalName = $review->suggestedCanonical->name;

        $review->executeMerge();

        $this->info("Merged \"{$importedName}\" -> \"{$canonicalName}\".");
    }

    private function applyParent(NutrientMappingReview $review): void
    {
        $nutrientName  = $review->sourceNutrient->name;
        $canonicalName = $review->suggestedCanonical->name;

        $review->executeParent();

        $this->info("Set \"{$canonicalName}\" as parent of \"{$nutrientName}\".");
    }

    private function applyKeep(NutrientMappingReview $review): void
    {
        $name = $review->sourceNutrient->name;
        $review->executeKeep();

        $this->info("Kept \"{$name}\" as a distinct nutrient.");
    }
}
