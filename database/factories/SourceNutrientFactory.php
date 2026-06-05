<?php

namespace Database\Factories;

use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

class SourceNutrientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'source_id'   => Source::factory(),
            'external_id' => (string) $this->faker->unique()->numerify('######'),
            'name'        => $this->faker->words(3, true),
            'description' => null,
            'nutrient_id' => null,
            'resolved_at' => null,
        ];
    }

    public function resolved(int $nutrientId): static
    {
        return $this->state([
            'nutrient_id' => $nutrientId,
            'resolved_at' => now(),
        ]);
    }
}
