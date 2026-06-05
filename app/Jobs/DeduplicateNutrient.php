<?php

namespace App\Jobs;

use App\AI\Contracts\LlmClientContract;
use App\AI\Tools\WebSearchTool;
use App\Models\Nutrient;
use App\Models\NutrientMappingReview;
use App\Models\SourceNutrient;
use App\Services\NutrientMergeService;
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

    public int $timeout;
    public int $tries = 2;

    public function __construct(
        public readonly SourceNutrient $sourceNutrient,
    ) {
        $this->timeout = (int) (config('ai.ollama.timeout') ?? 300);
    }

    public function handle(LlmClientContract $llm, WebSearchTool $search): void
    {
        if ($this->sourceNutrient->isResolved()) {
            return;
        }

        if (NutrientMappingReview::where('source_nutrient_id', $this->sourceNutrient->id)->exists()) {
            return;
        }

        $canonicals = Nutrient::pluck('name')->all();

        try {
            $snippets = $search->run([
                'query' => $this->sourceNutrient->name . ' nutrient explained',
                'limit' => 5,
            ]);
        } catch (\Throwable $e) {
            Log::warning('deduplicate_nutrient.search_failed', [
                'source_nutrient_id'   => $this->sourceNutrient->id,
                'source_nutrient_name' => $this->sourceNutrient->name,
                'error'                => $e->getMessage(),
            ]);
            NutrientMappingReview::create([
                'source_nutrient_id'     => $this->sourceNutrient->id,
                'suggested_canonical_id' => null,
                'confidence'             => 0,
                'decision_type'          => 'merge',
                'reasoning'              => 'Web search unavailable; manual classification required.',
                'status'                 => 'pending',
            ]);
            return;
        }

        $result     = $this->classify($llm, $canonicals, $snippets);
        $confidence = (int) ($result['confidence'] ?? 0);
        $matchName  = $result['match']     ?? null;
        $action     = $result['action']    ?? 'merge';
        $reasoning  = $result['reasoning'] ?? '';

        if ($matchName === null) {
            // LLM found no duplicate — this is a genuinely new substance; promote to canonical.
            NutrientMergeService::promote($this->sourceNutrient);
            Log::info('deduplicate_nutrient.promoted_as_new', [
                'source_nutrient_id'   => $this->sourceNutrient->id,
                'source_nutrient_name' => $this->sourceNutrient->name,
            ]);
            return;
        }

        $canonical = Nutrient::where('name', $matchName)->first();

        if ($canonical === null) {
            Log::warning('deduplicate_nutrient.unknown_match', [
                'source_nutrient_id'   => $this->sourceNutrient->id,
                'source_nutrient_name' => $this->sourceNutrient->name,
                'match'                => $matchName,
            ]);
            NutrientMergeService::promote($this->sourceNutrient);
            return;
        }

        if ($confidence >= 95) {
            if ($action === 'parent') {
                NutrientMergeService::promote($this->sourceNutrient, parentId: $canonical->id);
                Log::info('deduplicate_nutrient.classified_as_child', [
                    'source_nutrient_id'   => $this->sourceNutrient->id,
                    'source_nutrient_name' => $this->sourceNutrient->name,
                    'parent_id'            => $canonical->id,
                    'parent_name'          => $canonical->name,
                ]);
            } else {
                NutrientMergeService::merge($this->sourceNutrient, $canonical);
                Log::info('deduplicate_nutrient.merged', [
                    'source_nutrient_id'   => $this->sourceNutrient->id,
                    'source_nutrient_name' => $this->sourceNutrient->name,
                    'canonical_id'         => $canonical->id,
                    'canonical_name'       => $canonical->name,
                ]);
            }
        } else {
            NutrientMappingReview::create([
                'source_nutrient_id'     => $this->sourceNutrient->id,
                'suggested_canonical_id' => $canonical->id,
                'confidence'             => $confidence,
                'decision_type'          => $action === 'parent' ? 'parent' : 'merge',
                'reasoning'              => $reasoning,
                'status'                 => 'pending',
            ]);
            Log::info('deduplicate_nutrient.review_queued', [
                'source_nutrient_id'   => $this->sourceNutrient->id,
                'source_nutrient_name' => $this->sourceNutrient->name,
                'canonical_id'         => $canonical->id,
                'canonical_name'       => $canonical->name,
                'action'               => $action,
                'confidence'           => $confidence,
                'reasoning'            => $reasoning,
            ]);
        }
    }

    private function classify(LlmClientContract $llm, array $canonicals, array $snippets = []): array
    {
        $userContent = "Canonical nutrients:\n" . json_encode($canonicals)
            . "\n\nImported nutrient: " . json_encode($this->sourceNutrient->name);

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
                'content' => 'You are a nutrition data deduplication assistant. Given an imported nutrient name and a list of canonical nutrient names, determine the relationship between the imported nutrient and the canonicals. Choose one of two actions: "merge" if the imported nutrient is the same substance as a canonical under a different name or notation (true synonym, e.g. "Vitamin D (D2+D3)" → merge with "Vitamin D"); "parent" if the imported nutrient is a specific form, variant, or subtype of a canonical that should be placed as a child of it (e.g. "Phylloquinone" → parent "Vitamin K1", "18:2 n-6 c,c" → parent "Linoleic Acid (LA)", "Palmitic acid" → parent "Saturated Fat"). Output ONLY valid JSON with four keys: "match" (string: the canonical nutrient name if a relationship exists, null otherwise), "action" ("merge" or "parent", required when match is not null), "confidence" (integer 0–100), "reasoning" (string). No markdown, no code fences.',
            ],
            [
                'role'    => 'user',
                'content' => $userContent,
            ],
        ], [
            'model'   => config('ai.ollama.models.smart'),
            'options' => ['num_ctx' => 4096, 'num_predict' => 256, 'temperature' => 0.1],
        ]);

        return json_decode($response, true) ?? [];
    }
}
