<?php

namespace Tests\Unit\Ingredients;

use Tests\TestCase;
use App\Models\Unit;
use App\Models\Nutrient;
use App\Models\Ingredient;
use Illuminate\Support\Carbon;
use App\Jobs\SyncIngredientToSearch;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IngredientModelTest extends TestCase
{
    use RefreshDatabase;
    
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
    }

    public function test_nutrients_relationship(): void
    {
        $unit = Unit::firstOrCreate(
            ['name' => 'milligram', 'type' => 'mass'],
            ['abbreviation' => 'mg']
        );
        $ingredient = Ingredient::factory()->create();
        $nutrient = Nutrient::factory()->create();

        $ingredient->nutrients()->attach($nutrient->id, [
            'amount' => 5,
            'amount_unit_id' => $unit->id,
        ]);

        $this->assertCount(1, $ingredient->nutrients);
        $this->assertTrue($ingredient->nutrients->first()->is($nutrient));
    }

    public function test_deleting_ingredient_detaches_nutrients(): void
    {
        $unit = Unit::firstOrCreate(['name' => 'milligram', 'type' => 'mass'], ['abbreviation' => 'mg']);
        $ingredient = Ingredient::factory()->create();
        $nutrient = Nutrient::factory()->create();

        $ingredient->nutrients()->attach($nutrient->id, [
            'amount' => 5,
            'amount_unit_id' => $unit->id,
        ]);

        $this->assertDatabaseCount('ingredient_nutrient', 1);

        $ingredient->delete();

        // Pivot rows removed
        $this->assertDatabaseCount('ingredient_nutrient', 0);

        // Nutrient still exists
        $this->assertDatabaseHas('nutrients', ['id' => $nutrient->id]);
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
            return $job->ingredient->is($ingredient) && $job->action === 'delete';
        });

        // Restore triggers 'insert' job again
        $ingredient->restore();
        Queue::assertPushed(SyncIngredientToSearch::class, function ($job) use ($ingredient) {
            return $job->ingredient->is($ingredient) && $job->action === 'insert';
        });
    }
}
