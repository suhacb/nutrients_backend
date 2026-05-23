<?php

namespace App\Jobs;

use App\AI\Contracts\LlmClientContract;
use App\Models\Nutrient;
use App\Models\NutrientMappingReview;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeduplicateNutrient implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels, Batchable;

    public int $timeout = 120;
    public int $tries   = 2;

    public function __construct(
        public readonly Nutrient $nutrient,
    ) {}

    public function handle(LlmClientContract $llm): void
    {
        // Guard: skip if already merged (no source mappings) or review already pending
        if (!$this->nutrient->sourceMappings()->exists()) {
            return;
        }

        if (NutrientMappingReview::where('nutrient_id', $this->nutrient->id)->exists()) {
            return;
        }

        $canonicals = Nutrient::whereDoesntHave('sourceMappings')
            ->pluck('name')
            ->all();

        $response = $llm->chat([
            [
                'role'    => 'system',
                'content' => 'You are a nutrition data deduplication assistant. Given an imported nutrient name and a list of canonical nutrient names, determine if the imported nutrient is the same substance as any canonical nutrient. Output ONLY valid JSON with three keys: "match" (string: the canonical nutrient name if a match exists, null otherwise), "confidence" (integer 0–100), "reasoning" (string). No markdown, no code fences.',
            ],
            [
                'role'    => 'user',
                'content' => "Canonical nutrients:\n" . json_encode($canonicals)
                    . "\n\nImported nutrient: " . json_encode($this->nutrient->name),
            ],
        ]);

        $result     = json_decode($response, true) ?? [];
        $matchName  = $result['match']      ?? null;
        $confidence = (int) ($result['confidence'] ?? 0);
        $reasoning  = $result['reasoning']  ?? '';

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
                'nutrient_id'    => $this->nutrient->id,
                'canonical_id'   => $canonical->id,
                'confidence'     => $confidence,
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

    private function merge(Nutrient $canonical): void
    {
        DB::table('ingredient_nutrient')
            ->where('nutrient_id', $this->nutrient->id)
            ->update(['nutrient_id' => $canonical->id]);

        DB::table('nutrient_source_mappings')
            ->where('nutrient_id', $this->nutrient->id)
            ->update(['nutrient_id' => $canonical->id]);

        Nutrient::withoutEvents(fn () => $this->nutrient->forceDelete());
    }
}
