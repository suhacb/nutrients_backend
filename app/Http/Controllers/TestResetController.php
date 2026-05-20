<?php

namespace App\Http\Controllers;

use App\Jobs\SyncIngredientToSearch;
use App\Jobs\SyncNutrientToSearch;
use App\Jobs\SyncRecipeToSearch;
use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\Nutrient;
use App\Models\Recipe;
use Database\Seeders\TestDataSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use OpenApi\Attributes as OA;

class TestResetController extends Controller
{
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
        // Recipes cascade-delete their pivot rows, so delete them first.
        Recipe::withTrashed()->where('slug', 'not like', 'test-%')->get()->each(fn($r) => $r->forceDelete());

        // Null out brand_id on any ingredients that reference a non-fixture brand,
        // so those brands can be force-deleted without hitting BrandHasIngredientsException.
        $nonFixtureBrandIds = Brand::withTrashed()->where('slug', 'not like', 'test-%')->pluck('id');
        DB::table('ingredients')->whereIn('brand_id', $nonFixtureBrandIds)->update(['brand_id' => null]);

        // Remove any remaining pivot rows that reference non-fixture ingredients
        // (e.g. a non-fixture ingredient attached to a fixture recipe during a test).
        $nonFixtureIngredientIds = Ingredient::withTrashed()->where('slug', 'not like', 'test-%')->pluck('id');
        DB::table('recipe_ingredient')->whereIn('ingredient_id', $nonFixtureIngredientIds)->delete();

        Ingredient::withTrashed()->where('slug', 'not like', 'test-%')->get()->each(fn($i) => $i->forceDelete());

        // Remove non-fixture brands (no ingredients attached at this point).
        Brand::withTrashed()->where('slug', 'not like', 'test-%')->get()->each(fn($b) => $b->forceDelete());

        // Detach all nutrients from fixture ingredients so the seeder can re-attach
        // them in a clean state (handles the case where a test removed a relationship).
        DB::table('ingredient_nutrient')
            ->whereIn('ingredient_id', Ingredient::withTrashed()->where('slug', 'like', 'test-%')->pluck('id'))
            ->delete();

        // Restore soft-deleted fixtures so updateOrCreate in the seeder finds them.
        Brand::withTrashed()->where('slug', 'like', 'test-%')->whereNotNull('deleted_at')->restore();
        Ingredient::withTrashed()->where('slug', 'like', 'test-%')->whereNotNull('deleted_at')->restore();
        Recipe::withTrashed()->where('slug', 'like', 'test-%')->whereNotNull('deleted_at')->restore();

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

        Nutrient::all()->each(function (Nutrient $nutrient) {
            dispatch(new SyncNutrientToSearch($nutrient, 'insert'));
        });

        Ingredient::where('slug', 'like', 'test-%')->get()->each(function (Ingredient $ingredient) {
            dispatch(new SyncIngredientToSearch($ingredient->loadForSearch(), 'insert'));
        });

        Recipe::where('slug', 'like', 'test-%')->get()->each(function (Recipe $recipe) {
            dispatch(new SyncRecipeToSearch($recipe->loadForSearch(), 'insert'));
        });
    }
}
