<?php

namespace App\Console\Commands;

use App\AI\Contracts\LlmClientContract;
use App\Models\LabelNutrientMapping;
use App\Models\Nutrient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MapLabelNutrients extends Command
{
    protected $signature = 'app:map-label-nutrients
                            {--dry-run : Show proposed mappings without persisting}
                            {--key=*   : Only process specific label keys}';

    protected $description = 'Use LLM to map USDA label nutrient keys to canonical nutrients';

    private const LABEL_KEYS = [
        'fat', 'saturatedFat', 'transFat', 'carbohydrates', 'fiber',
        'sugars', 'protein', 'cholesterol', 'sodium', 'calcium', 'iron', 'calories',
    ];

    public function handle(LlmClientContract $llm): int
    {
        $keys = $this->resolveKeys();

        if (empty($keys)) {
            $this->info('No label keys to process.');
            return self::SUCCESS;
        }

        $canonicals = Nutrient::where('is_canonical', true)->get(['id', 'name']);

        if ($canonicals->isEmpty()) {
            $this->info('No canonical nutrients found. Run the canonical nutrients seeder first.');
            return self::SUCCESS;
        }

        $existing = LabelNutrientMapping::whereIn('label_key', $keys)->pluck('label_key')->all();
        $keys     = array_values(array_diff($keys, $existing));

        if (empty($keys)) {
            $this->info('All specified label keys already have mappings.');
            return self::SUCCESS;
        }

        $isDryRun = $this->option('dry-run');
        $mapped   = 0;

        $canonicalNames = $canonicals->pluck('name')->all();
        $canonicalsByName = $canonicals->keyBy('name');

        foreach ($keys as $labelKey) {
            $response = $llm->chat([
                [
                    'role'    => 'system',
                    'content' => 'You are a nutrition data expert. Given a label nutrient key from a USDA branded food label and a list of canonical nutrients, identify which canonical nutrient best matches this label key. Output ONLY valid JSON with three keys: "match" (string: the canonical nutrient name if a match exists, null otherwise), "confidence" (integer 0-100), "reasoning" (string). No markdown, no code fences.',
                ],
                [
                    'role'    => 'user',
                    'content' => "Label key: \"{$labelKey}\"\n\nCanonical nutrients:\n" . json_encode($canonicalNames),
                ],
            ], [
                'model'   => config('ai.ollama.models.smart'),
                'options' => ['num_ctx' => 4096, 'num_predict' => 256, 'temperature' => 0.1],
            ]);

            $result     = json_decode($response, true) ?? [];
            $matchName  = $result['match']      ?? null;
            $confidence = (int) ($result['confidence'] ?? 0);
            $reasoning  = $result['reasoning']  ?? '';

            if ($matchName === null) {
                $this->line("  {$labelKey} -> no match");
                continue;
            }

            $canonical = $canonicalsByName->get($matchName);

            if ($canonical === null) {
                Log::warning('map_label_nutrients.unknown_match', [
                    'label_key' => $labelKey,
                    'match'     => $matchName,
                ]);
                $this->line("  {$labelKey} -> unknown canonical \"{$matchName}\" (skipped)");
                continue;
            }

            $status = $confidence >= 95 ? 'approved' : 'pending';

            if ($isDryRun) {
                $this->comment($labelKey);
                $this->comment("  -> {$matchName} (confidence: {$confidence}, would be {$status})");
            } else {
                LabelNutrientMapping::create([
                    'label_key'   => $labelKey,
                    'nutrient_id' => $canonical->id,
                    'confidence'  => $confidence,
                    'reasoning'   => $reasoning,
                    'status'      => $status,
                ]);

                Log::info('map_label_nutrients.mapped', [
                    'label_key'    => $labelKey,
                    'nutrient_id'  => $canonical->id,
                    'nutrient_name'=> $canonical->name,
                    'confidence'   => $confidence,
                    'status'       => $status,
                ]);
            }

            $mapped++;
        }

        if ($isDryRun) {
            $this->info("Dry run: {$mapped} label key(s) would be mapped.");
        } else {
            $this->info("Done. {$mapped} label key(s) mapped.");
        }

        return self::SUCCESS;
    }

    private function resolveKeys(): array
    {
        $requested = array_filter(array_map('strval', $this->option('key')));

        if (empty($requested)) {
            return self::LABEL_KEYS;
        }

        return array_values(array_intersect($requested, self::LABEL_KEYS));
    }
}
