<?php

namespace App\Console\Commands;

use App\AI\Contracts\LlmClientContract;
use App\Models\Ingredient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BeautifyIngredientNames extends Command
{
    protected $signature = 'ingredients:beautify-names
                            {--dry-run : Show proposed changes without persisting them}
                            {--resume  : Skip ingredients already updated today}
                            {--id=*   : Only process specific ingredient IDs}';

    protected $description = 'Use LLM to reformat raw USDA all-caps ingredient names into properly cased titles';

    public function handle(LlmClientContract $llm): int
    {
        $query = Ingredient::query()->select(['id', 'name']);

        $ids = array_filter(array_map('intval', $this->option('id')));
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }

        if ($this->option('resume')) {
            $query->where('updated_at', '<', Carbon::today());
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('No ingredients to process.');
            return self::SUCCESS;
        }

        $this->info("Processing {$total} ingredient(s)...");

        $isDryRun          = $this->option('dry-run');
        $renamed           = 0;
        $totalInputTokens  = 0;
        $totalOutputTokens = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%  |  tokens in: %input_tokens% out: %output_tokens%');
        $bar->setMessage('0', 'input_tokens');
        $bar->setMessage('0', 'output_tokens');
        $bar->start();

        $query->chunkById(20, function ($batch) use ($llm, $isDryRun, &$renamed, &$totalInputTokens, &$totalOutputTokens, $bar) {
            $payload = $batch->map(fn ($i) => ['id' => $i->id, 'name' => $i->name])->values()->all();

            try {
                $response = $llm->chat([
                    [
                        'role'    => 'system',
                        'content' => 'You are a food naming expert for a nutrition database. Given a JSON array of USDA ingredient name objects (all-caps format), reformat each name to natural, readable form. Rules: (1) The first word of each comma-separated segment is capitalized. (2) Brand names and trademarks are title-cased. (3) Abbreviations and acronyms (US, IU, NFS, etc.) remain uppercase. (4) Specific product variant names and grade designations (e.g. "Extra Virgin", "Peanut Butter Cookie") are title-cased. (5) Generic food form or type descriptors that follow a specific product name are lowercase (e.g. "bars", "lean meat", "cooked", "frozen"). Example: "Candies, MARS SNACKFOOD US, TWIX Peanut Butter Cookie Bars" → "Candies, Mars Snackfood US, Twix Peanut Butter Cookie bars". Return a JSON array with the same IDs. Output ONLY valid JSON. No explanation, no markdown, no code fences.',
                    ],
                    [
                        'role'    => 'user',
                        'content' => json_encode($payload),
                    ],
                ], [
                    'model'   => config('ai.ollama.models.fast'),
                    'options' => ['num_ctx' => 2048, 'num_predict' => 512, 'temperature' => 0.0],
                ]);
            } catch (\Throwable $e) {
                $this->warn("\nBatch failed (IDs {$batch->first()->id}–{$batch->last()->id}): {$e->getMessage()}");
                $bar->advance($batch->count());
                return;
            }

            $usage              = $llm->getLastUsage();
            $totalInputTokens  += $usage['input'];
            $totalOutputTokens += $usage['output'];
            $bar->setMessage(number_format($totalInputTokens), 'input_tokens');
            $bar->setMessage(number_format($totalOutputTokens), 'output_tokens');

            $beautified = json_decode($response, true) ?? [];

            if (empty($beautified)) {
                $this->warn("\nLLM returned empty or invalid JSON for a batch. Raw response: " . mb_substr($response, 0, 300));
                $bar->advance($batch->count());
                return;
            }

            foreach ($beautified as $entry) {
                $ingredient = $batch->firstWhere('id', $entry['id'] ?? null);
                if (!$ingredient || empty($entry['name'] ?? null)) {
                    continue;
                }

                if ($isDryRun) {
                    $this->newLine();
                    $this->comment($ingredient->name);
                    $this->comment("  -> {$entry['name']}");
                } else {
                    Ingredient::withoutEvents(fn () => $ingredient->update(['name' => $entry['name']]));
                }

                $renamed++;
            }

            $bar->advance($batch->count());
        });

        $bar->finish();
        $this->newLine();
        $this->info('Tokens — input: ' . number_format($totalInputTokens) . ' / output: ' . number_format($totalOutputTokens) . ' / total: ' . number_format($totalInputTokens + $totalOutputTokens));

        if ($isDryRun) {
            $this->info("Dry run: {$renamed} ingredient(s) would be renamed.");
        } else {
            $this->info("Done. {$renamed} ingredient(s) renamed.");
        }

        return self::SUCCESS;
    }
}
