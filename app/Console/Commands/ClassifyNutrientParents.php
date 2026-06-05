<?php

namespace App\Console\Commands;

use App\AI\Contracts\LlmClientContract;
use App\Models\Nutrient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ClassifyNutrientParents extends Command
{
    protected $signature = 'nutrients:classify-parents
                            {--dry-run : Show proposed classifications without persisting}
                            {--id=*   : Only process specific nutrient IDs}';

    protected $description = 'Use LLM to assign a parent to each unclassified nutrient, one at a time';

    public function handle(LlmClientContract $llm): int
    {
        $query = Nutrient::query();

        $ids = array_filter(array_map('intval', $this->option('id')));
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            $query->whereNull('parent_id')
                ->whereHas('sourceNutrients');
        }

        $nutrients = $query->get(['id', 'name', 'slug', 'description']);

        if ($nutrients->isEmpty()) {
            $this->info('No unclassified nutrients to process.');
            return self::SUCCESS;
        }

        $isDryRun  = $this->option('dry-run');
        $classified = 0;

        // Only system-source hierarchy nodes are valid parents — keeps prompts small.
        $hierarchy = Nutrient::whereDoesntHave('sourceNutrients')
            ->get(['id', 'name'])
            ->map(fn ($n) => ['id' => $n->id, 'name' => $n->name])
            ->values()
            ->all();

        $validIds = array_column($hierarchy, 'id');

        foreach ($nutrients as $nutrient) {
            $response = $llm->chat([
                [
                    'role'    => 'system',
                    'content' => 'You are a nutrition data classification assistant. Given a list of possible parent nutrients and a nutrient to classify, assign the nutrient to its most appropriate parent. Output ONLY valid JSON with three keys: "parent_id" (integer: the chosen parent\'s ID from the list), "confidence" (integer 0–100), "reasoning" (string). No markdown, no code fences.',
                ],
                [
                    'role'    => 'user',
                    'content' => "Parent hierarchy:\n" . json_encode($hierarchy)
                        . "\n\nClassify this nutrient:\n" . json_encode([
                            'id'          => $nutrient->id,
                            'name'        => $nutrient->name,
                            'slug'        => $nutrient->slug,
                            'description' => $nutrient->description,
                        ]),
                ],
            ], [
                'model'   => config('ai.ollama.models.fast'),
                'options' => ['num_ctx' => 4096, 'num_predict' => 256, 'temperature' => 0.1],
            ]);

            $result     = json_decode($response, true) ?? [];
            $parentId   = $result['parent_id']   ?? null;
            $confidence = (int) ($result['confidence'] ?? 0);

            if (!in_array($parentId, $validIds)) {
                Log::info('classify_parent.invalid_or_missing', [
                    'nutrient_id' => $nutrient->id,
                    'parent_id'   => $parentId,
                ]);
                continue;
            }

            if ($confidence < 80) {
                Log::info('classify_parent.low_confidence', [
                    'nutrient_id' => $nutrient->id,
                    'confidence'  => $confidence,
                ]);
                continue;
            }

            if ($isDryRun) {
                $parentName = collect($hierarchy)->firstWhere('id', $parentId)['name'] ?? "#{$parentId}";
                $this->comment($nutrient->name);
                $this->comment("  -> {$parentName} (confidence: {$confidence}%)");
            } else {
                Nutrient::withoutEvents(fn () => $nutrient->update(['parent_id' => $parentId]));
                Log::info('classify_parent.assigned', [
                    'nutrient_id' => $nutrient->id,
                    'parent_id'   => $parentId,
                    'confidence'  => $confidence,
                ]);
            }

            $classified++;
        }

        if ($isDryRun) {
            $this->info("Dry run: {$classified} nutrient(s) would be classified.");
        } else {
            $this->info("Done. {$classified} nutrient(s) classified.");
        }

        return self::SUCCESS;
    }
}
