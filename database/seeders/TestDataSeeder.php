<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $gram = Unit::firstOrCreate(
            ['abbreviation' => 'g', 'type' => 'mass'],
            ['name' => 'gram']
        );

        $chicken = Ingredient::updateOrCreate(
            ['slug' => 'test-chicken-breast'],
            [
                'name'                   => 'Test Chicken Breast',
                'slug'                   => 'test-chicken-breast',
                'source'                 => 'test',
                'class'                  => 'final',
                'default_amount'         => 100.0,
                'default_amount_unit_id' => $gram->id,
            ]
        );

        $rice = Ingredient::updateOrCreate(
            ['slug' => 'test-white-rice'],
            [
                'name'                   => 'Test White Rice',
                'slug'                   => 'test-white-rice',
                'source'                 => 'test',
                'class'                  => 'final',
                'default_amount'         => 100.0,
                'default_amount_unit_id' => $gram->id,
            ]
        );

        Ingredient::updateOrCreate(
            ['slug' => 'test-olive-oil'],
            [
                'name'                   => 'Test Olive Oil',
                'slug'                   => 'test-olive-oil',
                'source'                 => 'test',
                'class'                  => 'final',
                'default_amount'         => 10.0,
                'default_amount_unit_id' => $gram->id,
            ]
        );

        $recipe = Recipe::updateOrCreate(
            ['slug' => 'test-grilled-chicken-with-rice'],
            [
                'name'        => 'Test Grilled Chicken with Rice',
                'slug'        => 'test-grilled-chicken-with-rice',
                'description' => 'A test recipe for e2e testing.',
                'portions'    => 2,
            ]
        );

        if (!$recipe->ingredients()->where('ingredient_id', $chicken->id)->exists()) {
            $recipe->ingredients()->attach($chicken->id, ['amount' => 200, 'unit_id' => $gram->id]);
        }

        if (!$recipe->ingredients()->where('ingredient_id', $rice->id)->exists()) {
            $recipe->ingredients()->attach($rice->id, ['amount' => 150, 'unit_id' => $gram->id]);
        }
    }
}
