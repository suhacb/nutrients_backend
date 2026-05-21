<?php

namespace App\Http\Controllers;

use App\Http\Resources\IngredientResource;
use App\Http\Resources\NutrientResource;
use App\Http\Resources\RecipeResource;
use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\Nutrient;
use App\Models\Recipe;
use App\Services\Search\SearchServiceContract;
use Database\Seeders\TestDataSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use OpenApi\Attributes as OA;

class TestResetController extends Controller
{
    public function __construct(private SearchServiceContract $search) {}

    #[OA\Post(
        path: '/api/test/reset',
        summary: 'Reset e2e state: remove non-fixture data, restore and re-seed fixtures, re-sync Zinc (E2E only)',
        tags: ['E2E'],
        responses: [
            new OA\Response(response: 200, description: 'Reset complete', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'reset', type: 'boolean', example: true)]
            )),
            new OA\Response(response: 404, description: 'Not available — APP_TEST_MODE is false'),
        ]
    )]
    public function reset(): JsonResponse
    {
        // Suppress model events during cleanup so forceDelete() does not trigger
        // Zinc sync jobs against an empty index (we re-sync everything below).
        Recipe::withoutEvents(function () {
            Recipe::withTrashed()->where('slug', 'not like', 'test-%')->get()->each(fn($r) => $r->forceDelete());
        });

        // Null out brand_id on any ingredients that reference a non-fixture brand,
        // so those brands can be force-deleted without hitting BrandHasIngredientsException.
        $nonFixtureBrandIds = Brand::withTrashed()->where('slug', 'not like', 'test-%')->pluck('id');
        DB::table('ingredients')->whereIn('brand_id', $nonFixtureBrandIds)->update(['brand_id' => null]);

        // Remove any remaining pivot rows that reference non-fixture ingredients
        // (e.g. a non-fixture ingredient attached to a fixture recipe during a test).
        $nonFixtureIngredientIds = Ingredient::withTrashed()->where('slug', 'not like', 'test-%')->pluck('id');
        DB::table('recipe_ingredient')->whereIn('ingredient_id', $nonFixtureIngredientIds)->delete();

        Ingredient::withoutEvents(function () {
            Ingredient::withTrashed()->where('slug', 'not like', 'test-%')->get()->each(fn($i) => $i->forceDelete());
        });

        Brand::withoutEvents(function () {
            // Remove non-fixture brands (no ingredients attached at this point).
            Brand::withTrashed()->where('slug', 'not like', 'test-%')->get()->each(fn($b) => $b->forceDelete());
        });

        // Detach all nutrients from fixture ingredients so the seeder can re-attach
        // them in a clean state (handles the case where a test removed a relationship).
        DB::table('ingredient_nutrient')
            ->whereIn('ingredient_id', Ingredient::withTrashed()->where('slug', 'like', 'test-%')->pluck('id'))
            ->delete();

        // Restore soft-deleted fixtures so updateOrCreate in the seeder finds them.
        // Suppress model events: the restored event would dispatch inline Zinc sync
        // jobs (in test mode the queue is sync), but those syncs are wasted because
        // syncFixturesToZinc() recreates and refills the indices immediately after.
        Brand::withoutEvents(fn () => Brand::withTrashed()->where('slug', 'like', 'test-%')->whereNotNull('deleted_at')->restore());
        Ingredient::withoutEvents(fn () => Ingredient::withTrashed()->where('slug', 'like', 'test-%')->whereNotNull('deleted_at')->restore());
        Recipe::withoutEvents(fn () => Recipe::withTrashed()->where('slug', 'like', 'test-%')->whereNotNull('deleted_at')->restore());

        Artisan::call('db:seed', ['--class' => TestDataSeeder::class, '--force' => true]);

        $this->syncFixturesToZinc();

        return response()->json(['reset' => true]);
    }

    private function syncFixturesToZinc(): void
    {
        $baseUrl  = config('zinc.base_url');
        $username = config('zinc.username');
        $password = config('zinc.password');

        foreach (config('zinc.index_definitions') as $key => $definition) {
            $name = config("zinc.indices.{$key}");
            Http::withBasicAuth($username, $password)->delete("{$baseUrl}/api/index/{$name}");
            Http::withBasicAuth($username, $password)->put("{$baseUrl}/api/index", array_merge(['name' => $name], $definition));
        }

        $nutrients = Nutrient::all()->mapWithKeys(
            fn (Nutrient $n) => [$n->id => (new NutrientResource($n->loadForSearch()))->resolve()]
        )->all();
        $this->search->bulkInsert(config('zinc.indices.nutrients'), $nutrients);
        $this->waitForSearchable(config('zinc.indices.nutrients'), 'Test Nutrient');

        $ingredients = Ingredient::where('slug', 'like', 'test-%')->get()->mapWithKeys(
            fn (Ingredient $i) => [$i->id => (new IngredientResource($i->loadForSearch()))->resolve()]
        )->all();
        $this->search->bulkInsert(config('zinc.indices.ingredients'), $ingredients);
        $this->waitForSearchable(config('zinc.indices.ingredients'), 'Test Chicken');

        $recipes = Recipe::where('slug', 'like', 'test-%')->get()->mapWithKeys(
            fn (Recipe $r) => [$r->id => (new RecipeResource($r->loadForSearch()))->resolve()]
        )->all();
        $this->search->bulkInsert(config('zinc.indices.recipes'), $recipes);
        $this->waitForSearchable(config('zinc.indices.recipes'), 'Test Grilled');
    }

    private function waitForSearchable(string $index, string $query, int $attempts = 20, int $delayMs = 250): void
    {
        for ($i = 0; $i < $attempts; $i++) {
            $result = $this->search->search($index, $query, 1, 1);
            if ($result->total > 0) {
                return;
            }
            usleep($delayMs * 1000);
        }

        throw new \RuntimeException("Zinc index '{$index}' not searchable after {$attempts} attempts ({$query})");
    }
}
