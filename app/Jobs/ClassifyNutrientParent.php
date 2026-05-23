<?php

namespace App\Jobs;

use App\AI\Contracts\LlmClientContract;
use App\Models\Nutrient;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ClassifyNutrientParent implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels, Batchable;

    public int $timeout = 60;
    public int $tries   = 2;

    public function __construct(
        public readonly Nutrient $nutrient,
    ) {}

    public function handle(LlmClientContract $llm): void
    {
        $hierarchy = Nutrient::whereDoesntHave('sourceMappings')
            ->get(['id', 'name'])
            ->map(fn ($n) => ['id' => $n->id, 'name' => $n->name])
            ->values()
            ->all();

        $validIds = array_column($hierarchy, 'id');

        $response = $llm->chat([
            [
                'role'    => 'system',
                'content' => 'You are a nutrition data classification assistant. Given a list of possible parent nutrients and a nutrient to classify, assign the nutrient to its most appropriate parent. Output ONLY valid JSON with three keys: "parent_id" (integer: the chosen parent\'s ID from the list), "confidence" (integer 0–100), "reasoning" (string). No markdown, no code fences.',
            ],
            [
                'role'    => 'user',
                'content' => "Parent hierarchy:\n" . json_encode($hierarchy)
                    . "\n\nClassify this nutrient:\n" . json_encode([
                        'id'          => $this->nutrient->id,
                        'name'        => $this->nutrient->name,
                        'description' => $this->nutrient->description,
                    ]),
            ],
        ]);

        $result     = json_decode($response, true) ?? [];
        $parentId   = $result['parent_id']   ?? null;
        $confidence = (int) ($result['confidence'] ?? 0);

        if (!in_array($parentId, $validIds)) {
            Log::warning('classify_parent.invalid_parent', [
                'nutrient_id' => $this->nutrient->id,
                'parent_id'   => $parentId,
            ]);
            return;
        }

        if ($confidence < 80) {
            Log::info('classify_parent.low_confidence', [
                'nutrient_id' => $this->nutrient->id,
                'confidence'  => $confidence,
            ]);
            return;
        }

        Nutrient::withoutEvents(fn () => $this->nutrient->update(['parent_id' => $parentId]));

        Log::info('classify_parent.assigned', [
            'nutrient_id' => $this->nutrient->id,
            'parent_id'   => $parentId,
            'confidence'  => $confidence,
        ]);
    }
}
