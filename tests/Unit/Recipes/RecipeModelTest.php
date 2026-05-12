<?php

namespace Tests\Unit\Recipes;

use App\Enums\SyncStatus;
use App\Jobs\SyncRecipeToSearch;
use App\Models\DietTag;
use App\Models\Ingredient;
use App\Models\Nutrient;
use App\Models\Recipe;
use App\Models\Unit;
use App\Traits\GeneratesSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\MakesUnit;
use Tests\TestCase;

class RecipeModelTest extends TestCase
{
    use RefreshDatabase, MakesUnit;

    public function test_fillable_attributes(): void
    {
        $this->assertEquals(
            ['name', 'slug', 'description', 'instructions', 'portions', 'source_url'],
            (new Recipe())->getFillable()
        );
    }

    public function test_portions_is_cast_to_integer(): void
    {
        $this->assertArrayHasKey('portions', (new Recipe())->getCasts());
        $this->assertSame('integer', (new Recipe())->getCasts()['portions']);
    }

    public function test_sync_status_is_cast_to_enum(): void
    {
        $this->assertArrayHasKey('sync_status', (new Recipe())->getCasts());
        $this->assertSame(SyncStatus::class, (new Recipe())->getCasts()['sync_status']);
    }

    public function test_uses_generates_slug_trait(): void
    {
        $this->assertContains(GeneratesSlug::class, class_uses_recursive(Recipe::class));
    }

    public function test_slug_is_auto_generated_on_create(): void
    {
        Queue::fake();
        $recipe = Recipe::factory()->create(['name' => 'Chicken Tikka Masala']);

        $this->assertEquals('chicken-tikka-masala', $recipe->slug);
    }

    public function test_uses_soft_deletes(): void
    {
        Queue::fake();
        $recipe = Recipe::factory()->create();
        $id     = $recipe->id;

        $recipe->delete();

        $this->assertNull(Recipe::find($id));
        $this->assertNotNull(Recipe::withTrashed()->find($id));
        $this->assertNotNull(Recipe::withTrashed()->find($id)->deleted_at);
    }

    public function test_sync_status_is_not_mass_assignable(): void
    {
        $recipe = new Recipe();
        $recipe->fill(['sync_status' => SyncStatus::Synced]);

        $this->assertNull($recipe->sync_status);
    }

    public function test_created_event_dispatches_insert_job(): void
    {
        Queue::fake();

        $recipe = Recipe::factory()->create();

        Queue::assertPushed(SyncRecipeToSearch::class, fn($job) =>
            $job->id === $recipe->id && $job->action === 'insert'
        );
    }

    public function test_updated_event_dispatches_update_job_and_resets_sync_status(): void
    {
        Queue::fake();
        $recipe = Recipe::factory()->create();
        DB::table('recipes')->where('id', $recipe->id)->update(['sync_status' => 'synced']);

        Queue::fake();
        $recipe->update(['name' => 'Updated Recipe Name']);

        $this->assertSame('pending', DB::table('recipes')->where('id', $recipe->id)->value('sync_status'));
        Queue::assertPushed(SyncRecipeToSearch::class, fn($job) =>
            $job->id === $recipe->id && $job->action === 'update'
        );
    }

    public function test_updating_only_sync_status_does_not_dispatch_job(): void
    {
        Queue::fake();
        $recipe = Recipe::factory()->create();

        Queue::fake();
        $recipe->sync_status = SyncStatus::Synced;
        $recipe->save();

        Queue::assertNotPushed(SyncRecipeToSearch::class);
    }

    public function test_deleted_event_dispatches_delete_job_and_resets_sync_status(): void
    {
        Queue::fake();
        $recipe = Recipe::factory()->create();
        DB::table('recipes')->where('id', $recipe->id)->update(['sync_status' => 'synced']);

        Queue::fake();
        $recipe->delete();

        $this->assertSame('pending', DB::table('recipes')->where('id', $recipe->id)->value('sync_status'));
        Queue::assertPushed(SyncRecipeToSearch::class, fn($job) =>
            $job->id === $recipe->id && $job->action === 'delete'
        );
    }

    public function test_restored_event_dispatches_insert_job_and_resets_sync_status(): void
    {
        Queue::fake();
        $recipe = Recipe::factory()->create();
        $recipe->delete();
        DB::table('recipes')->where('id', $recipe->id)->update(['sync_status' => 'synced']);

        Queue::fake();
        $recipe->restore();

        $this->assertSame('pending', DB::table('recipes')->where('id', $recipe->id)->value('sync_status'));
        Queue::assertPushed(SyncRecipeToSearch::class, fn($job) =>
            $job->id === $recipe->id && $job->action === 'insert'
        );
    }

    public function test_ingredients_relationship(): void
    {
        Queue::fake();

        $unit       = $this->makeUnit();
        $recipe     = Recipe::factory()->create();
        $ingredient = Ingredient::factory()->create(['default_amount_unit_id' => $unit->id]);

        $recipe->ingredients()->attach($ingredient->id, [
            'amount'  => 200.0,
            'unit_id' => $unit->id,
        ]);

        $this->assertCount(1, $recipe->ingredients);
        $this->assertTrue($recipe->ingredients->first()->is($ingredient));
    }

    public function test_diet_tags_relationship(): void
    {
        Queue::fake();

        $recipe = Recipe::factory()->create();
        $tag    = DietTag::factory()->create();

        $recipe->dietTags()->attach($tag->id);

        $this->assertCount(1, $recipe->dietTags);
        $this->assertTrue($recipe->dietTags->first()->is($tag));
    }

    public function test_load_for_search_loads_expected_relationships(): void
    {
        Queue::fake();

        $unit       = $this->makeUnit();
        $recipe     = Recipe::factory()->create();
        $ingredient = Ingredient::factory()->create(['default_amount_unit_id' => $unit->id]);
        $nutrient   = Nutrient::factory()->create();
        $tag        = DietTag::factory()->create();

        $ingredient->nutrients()->attach($nutrient->id, ['amount' => 5.0, 'amount_unit_id' => $unit->id]);
        $recipe->ingredients()->attach($ingredient->id, ['amount' => 100.0, 'unit_id' => $unit->id]);
        $recipe->dietTags()->attach($tag->id);

        $fresh = Recipe::find($recipe->id)->loadForSearch();

        $this->assertTrue($fresh->relationLoaded('dietTags'));
        $this->assertTrue($fresh->relationLoaded('ingredients'));
        $this->assertTrue($fresh->ingredients->first()->relationLoaded('nutrients'));
        $this->assertTrue($fresh->ingredients->first()->pivot->relationLoaded('unit'));
    }

    public function test_compute_nutrient_profile_scales_by_amount_and_sums(): void
    {
        Queue::fake();

        // gram is the base unit, to_base_factor null → code uses ?? 1.0
        $gram     = Unit::create(['name' => 'gram', 'abbreviation' => 'g', 'type' => 'mass']);
        $nutrient = Nutrient::factory()->create();

        $ingredient = Ingredient::factory()->create(['default_amount_unit_id' => $gram->id]);
        $ingredient->nutrients()->attach($nutrient->id, [
            'amount'        => 10.0, // 10 units per 100 g
            'amount_unit_id' => $gram->id,
        ]);

        $recipe = Recipe::factory()->create(['portions' => 2]);
        $recipe->ingredients()->attach($ingredient->id, [
            'amount'  => 200.0, // 200 g → scale = 2 → nutrient total = 20
            'unit_id' => $gram->id,
        ]);

        $profile = Recipe::find($recipe->id)->computeNutrientProfile();

        $this->assertCount(1, $profile);
        $this->assertEquals($nutrient->id, $profile[0]['nutrient_id']);
        $this->assertEquals(20.0, $profile[0]['amount']);
        $this->assertEquals($gram->id, $profile[0]['unit_id']);
    }

    public function test_compute_nutrient_profile_applies_unit_to_base_factor(): void
    {
        Queue::fake();

        $gram = Unit::create(['name' => 'gram',     'abbreviation' => 'g',  'type' => 'mass']);
        $kg   = Unit::create(['name' => 'kilogram', 'abbreviation' => 'kg', 'type' => 'mass', 'to_base_factor' => 1000.0]);

        $nutrient   = Nutrient::factory()->create();
        $ingredient = Ingredient::factory()->create(['default_amount_unit_id' => $gram->id]);

        $ingredient->nutrients()->attach($nutrient->id, [
            'amount'         => 5.0, // 5 units per 100 g
            'amount_unit_id' => $gram->id,
        ]);

        $recipe = Recipe::factory()->create(['portions' => 1]);
        $recipe->ingredients()->attach($ingredient->id, [
            'amount'  => 0.5,  // 0.5 kg = 500 g → scale = 5 → total = 25
            'unit_id' => $kg->id,
        ]);

        $profile = Recipe::find($recipe->id)->computeNutrientProfile();

        $this->assertEquals(25.0, $profile[0]['amount']);
    }

    public function test_compute_nutrient_profile_sums_across_multiple_ingredients(): void
    {
        Queue::fake();

        $gram = Unit::create(['name' => 'gram', 'abbreviation' => 'g', 'type' => 'mass']);

        $nutrient    = Nutrient::factory()->create();
        $ingredientA = Ingredient::factory()->create(['default_amount_unit_id' => $gram->id]);
        $ingredientB = Ingredient::factory()->create(['default_amount_unit_id' => $gram->id]);

        // Ingredient A: 10 units/100g, used 100g → contributes 10
        $ingredientA->nutrients()->attach($nutrient->id, ['amount' => 10.0, 'amount_unit_id' => $gram->id]);
        // Ingredient B: 4 units/100g, used 50g → contributes 2
        $ingredientB->nutrients()->attach($nutrient->id, ['amount' => 4.0, 'amount_unit_id' => $gram->id]);

        $recipe = Recipe::factory()->create(['portions' => 1]);
        $recipe->ingredients()->attach($ingredientA->id, ['amount' => 100.0, 'unit_id' => $gram->id]);
        $recipe->ingredients()->attach($ingredientB->id, ['amount' => 50.0,  'unit_id' => $gram->id]);

        $profile = Recipe::find($recipe->id)->computeNutrientProfile();

        $this->assertCount(1, $profile);
        $this->assertEquals(12.0, $profile[0]['amount']); // 10 + 2
    }

    public function test_compute_nutrient_profile_per_portion_divides_by_portions(): void
    {
        Queue::fake();

        $gram     = Unit::create(['name' => 'gram', 'abbreviation' => 'g', 'type' => 'mass']);
        $nutrient = Nutrient::factory()->create();

        $ingredient = Ingredient::factory()->create(['default_amount_unit_id' => $gram->id]);
        $ingredient->nutrients()->attach($nutrient->id, ['amount' => 10.0, 'amount_unit_id' => $gram->id]);

        // 200g → total = 20, 4 portions → per portion = 5
        $recipe = Recipe::factory()->create(['portions' => 4]);
        $recipe->ingredients()->attach($ingredient->id, ['amount' => 200.0, 'unit_id' => $gram->id]);

        $perPortion = Recipe::find($recipe->id)->computeNutrientProfilePerPortion();

        $this->assertEquals(5.0, $perPortion[0]['amount']);
    }
}
