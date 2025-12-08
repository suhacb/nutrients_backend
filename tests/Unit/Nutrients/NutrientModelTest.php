<?php

namespace Tests\Unit\Nutrients;

use Tests\TestCase;
use App\Models\Unit;
use App\Models\Nutrient;
use App\Models\Ingredient;
use App\Jobs\SyncNutrientToSearch;
use Illuminate\Support\Facades\Bus;
use App\Exceptions\NutrientAttachedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\MakesUnit;

class NutrientModelTest extends TestCase
{
    use RefreshDatabase, MakesUnit;

    public function test_fillable_fields(): void
    {
        $expectedFillable = [
            'source',
            'external_id',
            'name',
            'description',
            'derivation_code',
            'derivation_description',
        ];

        $model = new Nutrient();

        $this->assertEquals(
            $expectedFillable,
            $model->getFillable(),
            'The $fillable fields do not match the expected ones'
        );
    }

    public function test_casts_fields(): void
    {
        $expectedCasts = [
            'id' => 'int',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];

        $model = new Nutrient();

        $this->assertEquals(
            $expectedCasts,
            $model->getCasts(),
            'The $casts fields do not match the expected ones'
        );
    }

    public function test_it_dispatches_insert_job_when_nutrient_is_created(): void
    {
        Bus::fake();

        $nutrient = Nutrient::factory()->create();

        Bus::assertDispatched(SyncNutrientToSearch::class, function ($job) use ($nutrient) {
            return $job->nutrient->is($nutrient) &&
                   $job->action === 'insert' &&
                   $job->queue === 'nutrients';
        });
    }

    public function test_it_dispatches_update_job_when_nutrient_is_updated(): void
    {
        Bus::fake();

        $nutrient = Nutrient::factory()->create();
        $nutrient->update(['name' => 'Updated Name']);

        Bus::assertDispatched(SyncNutrientToSearch::class, function ($job) use ($nutrient) {
            return $job->nutrient->is($nutrient) &&
                   $job->action === 'update' &&
                   $job->queue === 'nutrients';
        });
    }

    public function test_it_dispatches_delete_job_when_nutrient_is_deleted(): void
    {
        Bus::fake();

        $nutrient = Nutrient::factory()->create();
        $nutrient->delete();

        Bus::assertDispatched(SyncNutrientToSearch::class, function ($job) use ($nutrient) {
            return $job->nutrient->is($nutrient) &&
                   $job->action === 'delete' &&
                   $job->queue === 'nutrients';
        });
    }

    public function test_it_dispatches_insert_job_when_nutrient_is_restored(): void
    {
        Bus::fake();

        $nutrient = Nutrient::factory()->create();
        $nutrient->delete();
        $nutrient->restore();

        Bus::assertDispatched(SyncNutrientToSearch::class, function ($job) use ($nutrient) {
            return $job->nutrient->is($nutrient) &&
                   $job->action === 'insert' &&
                   $job->queue === 'nutrients';
        });
    }

    public function test_it_prevents_delete_if_attached_to_ingredient(): void
    {
        $this->expectException(NutrientAttachedException::class);
        $this->expectExceptionMessage('Cannot delete nutrient: it is attached to one or more ingredients.');

        $nutrient = Nutrient::factory()->create();

        $unit = $this->makeUnit();

        $ingredient = Ingredient::factory()->create();

        // Attach the nutrient to the ingredient
        $ingredient->nutrients()->attach($nutrient->id, [
            'amount' => 10,
            'amount_unit_id' => $unit->id,
        ]);

        // Attempt to delete the nutrient -> should throw NutrientAttachedException
        $nutrient->delete();

        // Assert the nutrient still exists in the database
        $this->assertDatabaseHas('nutrients', ['id' => $nutrient->id]);
    }
}
