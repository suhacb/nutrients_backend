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

    protected $description = 'Use LLM to assign parent nutrients to unclassified nutrients in batches of 20';

    public function handle(LlmClientContract $llm): int
    {
        $query = Nutrient::query();

        $ids = array_filter(array_map('intval', $this->option('id')));
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            $query->whereNull('parent_id');
        }

        $nutrients = $query->get(['id', 'name']);

        if ($nutrients->isEmpty()) {
            $this->info('No unclassified nutrients to process.');
            return self::SUCCESS;
        }

        $isDryRun    = $this->option('dry-run');
        $classified  = 0;
        $validIds    = Nutrient::pluck('id')->all();
        $allNutrients = Nutrient::get(['id', 'name'])
            ->map(fn ($n) => ['id' => $n->id, 'name' => $n->name])
            ->values()
            ->all();

        foreach ($nutrients->chunk(20) as $batch) {
            $payload  = $batch->map(fn ($n) => ['id' => $n->id, 'name' => $n->name])->values()->all();
            $response = $llm->chat([
                [
                    'role'    => 'system',
                    'content' => 'You are a nutrition data classification assistant. Given a list of all known nutrients and a batch of unclassified nutrients, assign each unclassified nutrient to its most appropriate parent from the known list. Output ONLY a valid JSON array of objects with "id" (the nutrient to classify) and "parent_id" (the chosen parent). No explanation, no markdown, no code fences.',
                ],
                [
                    'role'    => 'user',
                    'content' => "All nutrients:\n" . json_encode($allNutrients) . "\n\nClassify these:\n" . json_encode($payload),
                ],
            ]);

            $classifications = json_decode($response, true) ?? [];

            foreach ($classifications as $entry) {
                $nutrientId = $entry['id'] ?? null;
                $parentId   = $entry['parent_id'] ?? null;

                $nutrient = $batch->firstWhere('id', $nutrientId);

                if (!$nutrient || !in_array($parentId, $validIds)) {
                    continue;
                }

                if ($isDryRun) {
                    $parentName = Nutrient::find($parentId)?->name ?? "#{$parentId}";
                    $this->comment($nutrient->name);
                    $this->comment("  -> {$parentName}");
                } else {
                    Nutrient::withoutEvents(fn () => $nutrient->update(['parent_id' => $parentId]));
                    Log::info('classify_parent.assigned', [
                        'nutrient_id' => $nutrientId,
                        'parent_id'   => $parentId,
                    ]);
                }

                $classified++;
            }
        }

        if ($isDryRun) {
            $this->info("Dry run: {$classified} nutrient(s) would be classified.");
        } else {
            $this->info("Done. {$classified} nutrient(s) classified.");
        }

        return self::SUCCESS;
    }
}
