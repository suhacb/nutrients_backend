<?php

namespace App\Jobs;

use App\AI\Contracts\LlmClientContract;
use App\AI\Tools\WebSearchTool;
use App\Models\IngredientNutrientPivot;
use App\Models\Nutrient;
use App\Models\NutrientMappingReview;
use App\Models\NutrientSourcePivot;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeduplicateNutrient implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels, Batchable;

    public int $timeout = 120;
    public int $tries   = 2;

    public function __construct(
        public readonly Nutrient $nutrient,
    ) {}

    public function handle(LlmClientContract $llm, WebSearchTool $search): void
    {
        if (!$this->nutrient->sourceMappings()->exists()) {
            return;
        }

        if (NutrientMappingReview::where('nutrient_id', $this->nutrient->id)->exists()) {
            return;
        }

        $canonicals = Nutrient::whereDoesntHave('sourceMappings')->pluck('name')->all();

        $result     = $this->classify($llm, $canonicals);
        $confidence = (int) ($result['confidence'] ?? 0);

        if ($confidence < 95) {
            $snippets = $this->webContext($search);
            if (!empty($snippets)) {
                $result     = $this->classify($llm, $canonicals, $snippets);
                $confidence = (int) ($result['confidence'] ?? 0);
            }
        }

        $matchName = $result['match']     ?? null;
        $reasoning = $result['reasoning'] ?? '';

        if ($matchName === null) {
            return;
        }

        $canonical = Nutrient::whereDoesntHave('sourceMappings')
            ->where('name', $matchName)
            ->first();

        if ($canonical === null) {
            Log::warning('deduplicate_nutrient.unknown_match', [
                'nutrient_id' => $this->nutrient->id,
                'match'       => $matchName,
            ]);
            return;
        }

        if ($confidence >= 95) {
            $this->merge($canonical);
            Log::info('deduplicate_nutrient.auto_merged', [
                'nutrient_id'  => $this->nutrient->id,
                'canonical_id' => $canonical->id,
                'confidence'   => $confidence,
            ]);
        } else {
            NutrientMappingReview::create([
                'nutrient_id'            => $this->nutrient->id,
                'suggested_canonical_id' => $canonical->id,
                'confidence'             => $confidence,
                'decision_type'          => 'merge',
                'reasoning'              => $reasoning,
                'status'                 => 'pending',
            ]);
            Log::info('deduplicate_nutrient.review_queued', [
                'nutrient_id'  => $this->nutrient->id,
                'canonical_id' => $canonical->id,
                'confidence'   => $confidence,
            ]);
        }
    }

    private function classify(LlmClientContract $llm, array $canonicals, array $snippets = []): array
    {
        $userContent = "Canonical nutrients:\n" . json_encode($canonicals)
            . "\n\nImported nutrient: " . json_encode($this->nutrient->name);

        if (!empty($snippets)) {
            $userContent .= "\n\nAdditional context from web research:\n"
                . implode("\n\n", array_map(
                    fn ($s) => "- {$s['title']}: {$s['snippet']}",
                    $snippets
                ));
        }

        $response = $llm->chat([
            [
                'role'    => 'system',
                'content' => 'You are a nutrition data deduplication assistant. Given an imported nutrient name and a list of canonical nutrient names, determine if the imported nutrient is the same substance as any canonical nutrient. Output ONLY valid JSON with three keys: "match" (string: the canonical nutrient name if a match exists, null otherwise), "confidence" (integer 0–100), "reasoning" (string). No markdown, no code fences.',
            ],
            [
                'role'    => 'user',
                'content' => $userContent,
            ],
        ]);

        return json_decode($response, true) ?? [];
    }

    private function webContext(WebSearchTool $search): array
    {
        try {
            return $search->run(['query' => $this->nutrient->name]);
        } catch (\Throwable) {
            return [];
        }
    }

    private function merge(Nutrient $canonical): void
    {
        IngredientNutrientPivot::where('nutrient_id', $this->nutrient->id)
            ->update(['nutrient_id' => $canonical->id]);

        NutrientSourcePivot::where('nutrient_id', $this->nutrient->id)
            ->update(['nutrient_id' => $canonical->id]);

        Nutrient::withoutEvents(fn () => $this->nutrient->forceDelete());
    }
}
