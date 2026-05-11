<?php

namespace Tests\Unit\Ingredients;

use App\Enums\SyncStatus;
use App\Jobs\SyncIngredientToSearch;
use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\IngredientNutritionFact;
use App\Models\Nutrient;
use App\Models\Unit;
use App\Traits\GeneratesSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\MakesUnit;
use Tests\TestCase;
use Tests\UsesZincIndices;

class IngredientModelTest extends TestCase
{
    use RefreshDatabase, MakesUnit, UsesZincIndices;
    
    public function test_uses_generates_slug_trait(): void
    {
        $this->assertContains(GeneratesSlug::class, class_uses_recursive(Ingredient::class));
    }

    public function test_slug_is_auto_generated_on_create(): void
    {
        Queue::fake();
        $ingredient = Ingredient::factory()->create(['name' => 'Whole Milk']);

        $this->assertNotNull($ingredient->slug);
        $this->assertEquals('whole-milk', $ingredient->slug);
    }

    public function test_fillable_attributes(): void
    {
        $ingredient = new Ingredient();

        $expected = [
            'external_id',
            'source',
            'class',
            'name',
            'slug',
            'description',
            'default_amount',
            'default_amount_unit_id',
            'brand_id',
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

    public function test_sync_status_is_not_mass_assignable(): void
    {
        $ingredient = new Ingredient();
        $ingredient->fill(['sync_status' => SyncStatus::Synced]);

        $this->assertNull($ingredient->sync_status);
    }

    public function test_sync_status_is_cast_to_enum(): void
    {
        Queue::fake();
        $ingredient = Ingredient::factory()->create();

        $this->assertInstanceOf(SyncStatus::class, $ingredient->fresh()->sync_status);
        $this->assertSame(SyncStatus::Pending, $ingredient->fresh()->sync_status);
    }

    public function test_sync_status_cast_is_declared_in_casts_array(): void
    {
        $this->assertArrayHasKey('sync_status', (new Ingredient())->getCasts());
        $this->assertSame(SyncStatus::class, (new Ingredient())->getCasts()['sync_status']);
    }

    public function test_updated_event_resets_sync_status_to_pending_and_dispatches_job(): void
    {
        Queue::fake();
        $ingredient = Ingredient::factory()->create();
        DB::table('ingredients')->where('id', $ingredient->id)->update(['sync_status' => 'synced']);

        Queue::fake();
        $ingredient->update(['name' => 'Updated Name']);

        $this->assertSame('pending', DB::table('ingredients')->where('id', $ingredient->id)->value('sync_status'));
        Queue::assertPushed(SyncIngredientToSearch::class, fn($job) => $job->action === 'update');
    }

    public function test_deleted_event_resets_sync_status_to_pending_and_dispatches_job(): void
    {
        Queue::fake();
        $ingredient = Ingredient::factory()->create();
        DB::table('ingredients')->where('id', $ingredient->id)->update(['sync_status' => 'synced']);

        Queue::fake();
        $ingredient->delete();

        $this->assertSame('pending', DB::table('ingredients')->where('id', $ingredient->id)->value('sync_status'));
        Queue::assertPushed(SyncIngredientToSearch::class, fn($job) => $job->action === 'delete');
    }

    public function test_restored_event_resets_sync_status_to_pending_and_dispatches_job(): void
    {
        Queue::fake();
        $ingredient = Ingredient::factory()->create();
        $ingredient->delete();
        DB::table('ingredients')->where('id', $ingredient->id)->update(['sync_status' => 'synced']);

        Queue::fake();
        $ingredient->restore();

        $this->assertSame('pending', DB::table('ingredients')->where('id', $ingredient->id)->value('sync_status'));
        Queue::assertPushed(SyncIngredientToSearch::class, fn($job) => $job->action === 'insert');
    }

    public function test_updating_only_sync_status_does_not_dispatch_job(): void
    {
        Queue::fake();
        $ingredient = Ingredient::factory()->create();

        Queue::fake();
        $ingredient->sync_status = SyncStatus::Synced;
        $ingredient->save();

        Queue::assertNotPushed(SyncIngredientToSearch::class);
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

    public function test_load_for_search_loads_expected_relationships(): void
    {
        Queue::fake();

        $defaultUnit = $this->makeUnit();
        $amountUnit  = Unit::firstOrCreate(
            ['abbreviation' => 'mg'],
            ['name' => 'milligram', 'type' => 'mass']
        );
        $brand       = Brand::factory()->create();
        $nutrient    = Nutrient::factory()->create();
        $category    = IngredientCategory::factory()->create();

        $ingredient = Ingredient::factory()->create([
            'default_amount_unit_id' => $defaultUnit->id,
            'brand_id'               => $brand->id,
        ]);

        $ingredient->nutrients()->attach($nutrient->id, [
            'amount'         => 5,
            'amount_unit_id' => $amountUnit->id,
        ]);

        IngredientNutritionFact::factory()->create(['ingredient_id' => $ingredient->id, 'amount_unit_id' => $defaultUnit->id]);
        $ingredient->categories()->attach($category->id);

        $fresh = Ingredient::find($ingredient->id);
        $fresh->loadForSearch();

        $this->assertTrue($fresh->relationLoaded('brand'));
        $this->assertTrue($fresh->relationLoaded('default_amount_unit'));
        $this->assertTrue($fresh->relationLoaded('nutrients'));
        $this->assertTrue($fresh->relationLoaded('nutrition_facts'));
        $this->assertTrue($fresh->relationLoaded('categories'));

        $this->assertEquals($brand->id, $fresh->brand->id);
        $this->assertEquals($defaultUnit->id, $fresh->default_amount_unit->id);
        $this->assertCount(1, $fresh->nutrients);
        $this->assertCount(1, $fresh->nutrition_facts);
        $this->assertCount(1, $fresh->categories);
        $this->assertTrue($fresh->nutrients->first()->pivot->relationLoaded('amount_unit'));
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
        ]);

        // Trigger update to dispatch job
        $ingredient->update(['name' => 'Updated Name']);

        Queue::assertPushed(SyncIngredientToSearch::class, function ($job) use ($ingredient, $nutrient, $unit, $defaultUnit) {
            $loadedIngredient = $job->ingredient;

            // Relationships should be loaded
            $this->assertTrue($loadedIngredient->relationLoaded('nutrients'));
            $this->assertTrue($loadedIngredient->relationLoaded('default_amount_unit'));
            $this->assertTrue($loadedIngredient->relationLoaded('brand'));

            $pivot = $loadedIngredient->nutrients->first()->pivot;
            $this->assertTrue($pivot->relationLoaded('amount_unit'));

            // Optional: check IDs
            $this->assertEquals($nutrient->id, $loadedIngredient->nutrients->first()->id);
            $this->assertEquals($unit->id, $pivot->amount_unit->id);
            $this->assertEquals($defaultUnit->id, $loadedIngredient->default_amount_unit->id);

            return true;
        });
    }

    public function test_brand_relationship(): void
    {
        $brand      = Brand::factory()->create();
        $ingredient = Ingredient::factory()->create(['brand_id' => $brand->id]);

        $fresh = Ingredient::find($ingredient->id);

        $this->assertInstanceOf(Brand::class, $fresh->brand);
        $this->assertEquals($brand->id, $fresh->brand->id);
    }

    public function test_brand_is_nullable(): void
    {
        $ingredient = Ingredient::factory()->create(['brand_id' => null]);

        $this->assertNull(Ingredient::find($ingredient->id)->brand);
    }

    public function test_load_for_search_excludes_nutrient_descriptions(): void
    {
        Queue::fake();

        $unit     = $this->makeUnit();
        $nutrient = Nutrient::factory()->create(['description' => 'A long description that should not appear in the search payload.']);

        $ingredient = Ingredient::factory()->create(['default_amount_unit_id' => $unit->id]);
        $ingredient->nutrients()->attach($nutrient->id, ['amount' => 5, 'amount_unit_id' => $unit->id]);

        $payload = Ingredient::find($ingredient->id)->loadForSearch()->toArray();

        foreach ($payload['nutrients'] as $n) {
            $this->assertArrayNotHasKey('description', $n);
        }
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
