<?php

namespace Tests\Unit\Ingredients;

use Tests\TestCase;
use App\Models\Unit;
use App\Models\Nutrient;
use App\Models\Ingredient;
use Illuminate\Support\Carbon;
use App\Jobs\SyncIngredientToSearch;
use Illuminate\Support\Facades\Queue;
use App\Models\IngredientNutritionFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\MakesUnit;

class IngredientModelTest extends TestCase
{
    use RefreshDatabase, MakesUnit;

    public function setUp(): void
    {
        parent::setUp();
    }
    
    public function test_fillable_attributes(): void
    {
        $ingredient = new Ingredient();

        $expected = [
            'external_id',
            'source',
            'class',
            'name',
            'description',
            'default_amount',
            'default_amount_unit_id',
        ];

        $this->assertEquals($expected, $ingredient->getFillable());
    }

    public function test_casts(): void
    {
        Ingredient::withoutEvents(function() {
            $ingredient = Ingredient::factory()->create([
                'default_amount' => 123.45,
            ]);
    
            // default_amount is stored as float
            $this->assertIsFloat($ingredient->default_amount);
    
            // created_at / updated_at / deleted_at should be Carbon instances
            $this->assertInstanceOf(Carbon::class, $ingredient->created_at);
            $this->assertInstanceOf(Carbon::class, $ingredient->updated_at);
            $this->assertNull($ingredient->deleted_at);
    
            $ingredient->delete();
            $this->assertInstanceOf(Carbon::class, $ingredient->deleted_at);
        });
    }

    public function test_nutrients_relationship(): void
    {
        $unit = $this->makeUnit();
        $ingredient = Ingredient::factory()->create();
        $nutrient = Nutrient::factory()->create();

        $ingredient->nutrients()->attach($nutrient->id, [
            'amount' => 5,
            'amount_unit_id' => $unit->id,
        ]);

        $this->assertCount(1, $ingredient->nutrients);
        $this->assertTrue($ingredient->nutrients->first()->is($nutrient));
    }

    public function test_soft_deleting_ingredient_preserves_nutrient_pivot(): void
    {
        Ingredient::withoutEvents(function() {
            $unit = $this->makeUnit();
            $ingredient = Ingredient::factory()->create();
            $nutrient = Nutrient::factory()->create();
    
            $ingredient->nutrients()->attach($nutrient->id, [
                'amount' => 5,
                'amount_unit_id' => $unit->id,
            ]);
    
            $this->assertDatabaseCount('ingredient_nutrient', 1);
    
            // Soft delete the ingredient
            $ingredient->delete();
    
            // Pivot rows should remain
            $this->assertDatabaseCount('ingredient_nutrient', 1);
    
            // Nutrient itself still exists
            $this->assertDatabaseHas('nutrients', ['id' => $nutrient->id]);
    
            // Optional: restore ingredient and verify pivot relationship
            $ingredient->restore();
            $this->assertTrue($ingredient->nutrients()->where('nutrient_id', $nutrient->id)->exists());
        });
    }

    public function test_force_deleting_ingredient_detaches_nutrients(): void
    {
        Ingredient::withoutEvents(function () {
            $unit = $this->makeUnit();
            $ingredient = Ingredient::factory()->create();
            $nutrient = Nutrient::factory()->create();
    
            $ingredient->nutrients()->attach($nutrient->id, [
                'amount' => 5,
                'amount_unit_id' => $unit->id,
            ]);
    
            $this->assertDatabaseCount('ingredient_nutrient', 1);
    
            // Force delete the ingredient
            $ingredient->forceDelete();
    
            // Pivot rows should be removed
            $this->assertDatabaseCount('ingredient_nutrient', 0);
    
            // Nutrient itself still exists
            $this->assertDatabaseHas('nutrients', ['id' => $nutrient->id]);
        });
    }

    public function test_model_events_dispatch_jobs(): void
    {
        Queue::fake();

        $ingredient = Ingredient::factory()->create();

        // 'insert' job should be dispatched on creation
        Queue::assertPushed(SyncIngredientToSearch::class, function ($job) use ($ingredient) {
            return $job->ingredient->is($ingredient) && $job->action === 'insert';
        });

        // Update triggers 'update' job
        $ingredient->update(['name' => 'Updated Name']);
        Queue::assertPushed(SyncIngredientToSearch::class, function ($job) use ($ingredient) {
            return $job->ingredient->is($ingredient) && $job->action === 'update';
        });

        // Delete triggers 'delete' job
        $ingredient->delete();
        Queue::assertPushed(SyncIngredientToSearch::class, function ($job) use ($ingredient) {
            return ($job->ingredient?->is($ingredient) ?? true) // pass if null
                && $job->action === 'delete';
        });

        // Restore triggers 'insert' job again
        $ingredient->restore();
        Queue::assertPushed(SyncIngredientToSearch::class, function ($job) use ($ingredient) {
            // Pass if job has no model (nullable) OR model matches
            return ($job->ingredient?->is($ingredient) ?? true)
                && $job->action === 'insert';
        });
    }

    public function test_jobs_have_relationships_loaded_for_search(): void
    {
        Queue::fake();

        $unit = $this->makeUnit();
        $defaultUnit = Unit::inRandomOrder()->first();
        
        $ingredient = Ingredient::factory()->create([
            'default_amount_unit_id' => $defaultUnit->id
        ]);
        $nutrient = Nutrient::factory()->create();
        
        $ingredient->nutrients()->attach($nutrient->id, [
            'amount' => 5,
            'amount_unit_id' => $unit->id,
            'portion_amount' => 2,
            'portion_amount_unit_id' => $unit->id,
        ]);

        // Trigger update to dispatch job
        $ingredient->update(['name' => 'Updated Name']);

        Queue::assertPushed(SyncIngredientToSearch::class, function ($job) use ($ingredient, $nutrient, $unit, $defaultUnit) {
            $loadedIngredient = $job->ingredient;

            // Relationships should be loaded
            $this->assertTrue($loadedIngredient->relationLoaded('nutrients'));
            $this->assertTrue($loadedIngredient->relationLoaded('default_amount_unit'));

            $pivot = $loadedIngredient->nutrients->first()->pivot;
            $this->assertTrue($pivot->relationLoaded('amount_unit'));
            $this->assertTrue($pivot->relationLoaded('portion_amount_unit'));

            // Optional: check IDs
            $this->assertEquals($nutrient->id, $loadedIngredient->nutrients->first()->id);
            $this->assertEquals($unit->id, $pivot->amount_unit->id);
            $this->assertEquals($unit->id, $pivot->portion_amount_unit->id);
            $this->assertEquals($defaultUnit->id, $loadedIngredient->default_amount_unit->id);

            return true;
        });
    }

    public function test_nutrition_facts_relationship(): void
    {
        $ingredient = Ingredient::factory()->create();
        $unit = $this->makeUnit();

        $nutritionFact1 = IngredientNutritionFact::create([
            'ingredient_id' => $ingredient->id,
            'category' => 'macro',
            'name' => 'Protein',
            'amount' => 10.0,
            'amount_unit_id' => $unit->id,
        ]);

        $nutritionFact2 = IngredientNutritionFact::create([
            'ingredient_id' => $ingredient->id,
            'category' => 'micro',
            'name' => 'Vitamin A',
            'amount' => 0.2,
            'amount_unit_id' => $unit->id,
        ]);

        $this->assertCount(2, $ingredient->nutrition_facts);
        $this->assertTrue($ingredient->nutrition_facts->contains($nutritionFact1));
        $this->assertTrue($ingredient->nutrition_facts->contains($nutritionFact2));

        // Optional: check the relationship type
        $this->assertInstanceOf(IngredientNutritionFact::class, $ingredient->nutrition_facts->first());
    }
}
