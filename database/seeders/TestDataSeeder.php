<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\Nutrient;
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

        $kcal = Unit::firstOrCreate(
            ['abbreviation' => 'kcal', 'type' => 'energy'],
            ['name' => 'kilocalorie']
        );

        // Brands
        $natureFresh = Brand::updateOrCreate(
            ['slug' => 'test-nature-fresh'],
            [
                'name'    => 'Test Nature Fresh',
                'slug'    => 'test-nature-fresh',
                'owner'   => 'Nature Fresh Co.',
                'country' => 'United States',
            ]
        );

        $goldenHarvest = Brand::updateOrCreate(
            ['slug' => 'test-golden-harvest'],
            [
                'name'    => 'Test Golden Harvest',
                'slug'    => 'test-golden-harvest',
                'owner'   => 'Golden Harvest Ltd.',
                'country' => 'Canada',
            ]
        );

        Brand::updateOrCreate(
            ['slug' => 'test-artisan-kitchen'],
            [
                'name'    => 'Test Artisan Kitchen',
                'slug'    => 'test-artisan-kitchen',
                'owner'   => 'Artisan Kitchen GmbH',
                'country' => 'Germany',
            ]
        );

        // Nutrients (seeded by NutrientsTableSeeder via DatabaseSeeder)
        $protein = Nutrient::where('name', 'Protein')->first();
        $fat     = Nutrient::where('name', 'Fat')->first();
        $carbs   = Nutrient::where('name', 'Carbohydrates')->first();
        $energy  = Nutrient::where('name', 'Energy')->first();

        // Ingredients
        $chicken = Ingredient::updateOrCreate(
            ['slug' => 'test-chicken-breast'],
            [
                'name'                   => 'Test Chicken Breast',
                'slug'                   => 'test-chicken-breast',
                'source'                 => 'test',
                'class'                  => 'final',
                'default_amount'         => 100.0,
                'default_amount_unit_id' => $gram->id,
                'brand_id'               => $natureFresh->id,
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
                'brand_id'               => $goldenHarvest->id,
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

        // Ingredient-Nutrient relationships — chicken breast (per 100 g)
        if ($protein && !$chicken->nutrients()->where('nutrient_id', $protein->id)->exists()) {
            $chicken->nutrients()->attach($protein->id, ['amount' => 31.0, 'amount_unit_id' => $gram->id]);
        }
        if ($fat && !$chicken->nutrients()->where('nutrient_id', $fat->id)->exists()) {
            $chicken->nutrients()->attach($fat->id, ['amount' => 3.6, 'amount_unit_id' => $gram->id]);
        }
        if ($energy && !$chicken->nutrients()->where('nutrient_id', $energy->id)->exists()) {
            $chicken->nutrients()->attach($energy->id, ['amount' => 165.0, 'amount_unit_id' => $kcal->id]);
        }

        // Ingredient-Nutrient relationships — white rice (per 100 g cooked)
        if ($carbs && !$rice->nutrients()->where('nutrient_id', $carbs->id)->exists()) {
            $rice->nutrients()->attach($carbs->id, ['amount' => 28.0, 'amount_unit_id' => $gram->id]);
        }
        if ($protein && !$rice->nutrients()->where('nutrient_id', $protein->id)->exists()) {
            $rice->nutrients()->attach($protein->id, ['amount' => 2.7, 'amount_unit_id' => $gram->id]);
        }

        // Recipe
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
