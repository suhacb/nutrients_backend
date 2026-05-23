<?php

namespace App\Console\Commands;

use App\AI\Contracts\LlmClientContract;
use App\Models\Nutrient;
use App\Models\Source;
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
        $systemSourceId = Source::where('slug', 'system')->value('id');

        $query = Nutrient::query();

        $ids = array_filter(array_map('intval', $this->option('id')));
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            $query->whereNull('parent_id')
                ->whereHas('sourceMappings');
        }

        $nutrients = $query->get(['id', 'name', 'slug', 'description']);

        if ($nutrients->isEmpty()) {
            $this->info('No unclassified nutrients to process.');
            return self::SUCCESS;
        }

        $isDryRun  = $this->option('dry-run');
        $classified = 0;

        // Only system-source hierarchy nodes are valid parents — keeps prompts small.
        $hierarchy = Nutrient::whereDoesntHave('sourceMappings')
            ->get(['id', 'name'])
            ->map(fn ($n) => ['id' => $n->id, 'name' => $n->name])
            ->values()
            ->all();

        $validIds = array_column($hierarchy, 'id');

        foreach ($nutrients as $nutrient) {
            $response = $llm->chat([
                [
                    'role'    => 'system',
                    'content' => 'You are a nutrition data classification assistant. Given a list of possible parent nutrients and a nutrient to classify, assign the nutrient to its most appropriate parent. Output ONLY a valid JSON object with a single key "parent_id" containing the chosen parent\'s ID. No explanation, no markdown, no code fences.',
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
            ]);

            $result   = json_decode($response, true) ?? [];
            $parentId = $result['parent_id'] ?? null;

            if (!in_array($parentId, $validIds)) {
                continue;
            }

            if ($isDryRun) {
                $parentName = collect($hierarchy)->firstWhere('id', $parentId)['name'] ?? "#{$parentId}";
                $this->comment($nutrient->name);
                $this->comment("  -> {$parentName}");
            } else {
                Nutrient::withoutEvents(fn () => $nutrient->update(['parent_id' => $parentId]));
                Log::info('classify_parent.assigned', [
                    'nutrient_id' => $nutrient->id,
                    'parent_id'   => $parentId,
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
