<?php

namespace App\Console\Commands;

use App\AI\Contracts\LlmClientContract;
use App\Models\Ingredient;
use Illuminate\Console\Command;

class BeautifyIngredientNames extends Command
{
    protected $signature = 'ingredients:beautify-names
                            {--dry-run : Show proposed changes without persisting them}
                            {--id=*   : Only process specific ingredient IDs}';

    protected $description = 'Use LLM to reformat raw USDA all-caps ingredient names into properly cased titles';

    public function handle(LlmClientContract $llm): int
    {
        $query = Ingredient::query();

        $ids = array_filter(array_map('intval', $this->option('id')));
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }

        $ingredients = $query->get(['id', 'name']);

        if ($ingredients->isEmpty()) {
            $this->info('No ingredients to process.');
            return self::SUCCESS;
        }

        $isDryRun = $this->option('dry-run');
        $renamed  = 0;

        foreach ($ingredients->chunk(50) as $batch) {
            $payload  = $batch->map(fn ($i) => ['id' => $i->id, 'name' => $i->name])->values()->all();
            $response = $llm->chat([
                [
                    'role'    => 'system',
                    'content' => 'You are a data formatting assistant. Given a JSON array of ingredient name objects, return a JSON array with the same IDs but with names reformatted from USDA all-caps style to proper title case. Preserve brand names, trademarks, and proper nouns. Output ONLY valid JSON. No explanation, no markdown, no code fences.',
                ],
                [
                    'role'    => 'user',
                    'content' => json_encode($payload),
                ],
            ]);

            $beautified = json_decode($response, true) ?? [];

            foreach ($beautified as $entry) {
                $ingredient = $batch->firstWhere('id', $entry['id'] ?? null);
                if (!$ingredient) {
                    continue;
                }

                if ($isDryRun) {
                    $this->comment($ingredient->name);
                    $this->comment("  -> {$entry['name']}");
                } else {
                    Ingredient::withoutEvents(fn () => $ingredient->update(['name' => $entry['name']]));
                }

                $renamed++;
            }
        }

        if ($isDryRun) {
            $this->info("Dry run: {$renamed} ingredient(s) would be renamed.");
        } else {
            $this->info("Done. {$renamed} ingredient(s) renamed.");
        }

        return self::SUCCESS;
    }
}
