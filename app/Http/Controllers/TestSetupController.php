<?php

namespace App\Http\Controllers;

use App\Http\Resources\IngredientResource;
use App\Http\Resources\NutrientResource;
use App\Http\Resources\RecipeResource;
use App\Models\Ingredient;
use App\Models\Nutrient;
use App\Models\Recipe;
use App\Services\Search\SearchServiceContract;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TestDataSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use OpenApi\Attributes as OA;

class TestSetupController extends Controller
{
    public function __construct(private SearchServiceContract $search) {}

    #[OA\Post(
        path: '/api/test/setup',
        summary: 'Initialise e2e environment: fresh migrations, base seed, fixture seed, Zinc sync (E2E only)',
        tags: ['E2E'],
        responses: [
            new OA\Response(response: 200, description: 'Setup complete', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'setup', type: 'boolean', example: true)]
            )),
            new OA\Response(response: 404, description: 'Not available — APP_TEST_MODE is false'),
            new OA\Response(response: 500, description: 'Setup failed', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'error', type: 'string')]
            )),
        ]
    )]
    public function setup(): JsonResponse
    {
        try {
            $this->resetZincIndices();

            Artisan::call('migrate:fresh', ['--force' => true]);
            Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
            Artisan::call('db:seed', ['--class' => TestDataSeeder::class, '--force' => true]);

            $this->syncFixturesToZinc();

            return response()->json(['setup' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function resetZincIndices(): void
    {
        $baseUrl  = config('zinc.base_url');
        $username = config('zinc.username');
        $password = config('zinc.password');

        foreach (config('zinc.index_definitions') as $key => $definition) {
            $name = config("zinc.indices.{$key}");
            Http::withBasicAuth($username, $password)->delete("{$baseUrl}/api/index/{$name}");
            Http::withBasicAuth($username, $password)->put("{$baseUrl}/api/index", array_merge(['name' => $name], $definition));
        }
    }

    private function syncFixturesToZinc(): void
    {
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
