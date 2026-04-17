<?php

namespace Database\Factories;

use App\Models\Nutrient;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Nutrient>
 */
class NutrientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'source_id'   => Source::factory(),
            'external_id' => function (array $attrs) {
                do {
                    $number = fake()->numberBetween(1, 999999);
                    $exists = Nutrient::where([
                        'external_id' => $number,
                        'source_id'   => $attrs['source_id'],
                    ])->exists();
                } while ($exists);
                return strval($number);
            },
            'name'        => fake()->name(),
            'description' => fake()->paragraph(),
        ];
    }
}