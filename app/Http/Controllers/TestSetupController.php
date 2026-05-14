<?php

namespace App\Http\Controllers;

use App\Jobs\SyncIngredientToSearch;
use App\Jobs\SyncRecipeToSearch;
use App\Models\Ingredient;
use App\Models\Recipe;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TestDataSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class TestSetupController extends Controller
{
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
        Ingredient::where('slug', 'like', 'test-%')->get()->each(function (Ingredient $ingredient) {
            dispatch(new SyncIngredientToSearch($ingredient->loadForSearch(), 'insert'));
        });

        Recipe::where('slug', 'like', 'test-%')->get()->each(function (Recipe $recipe) {
            dispatch(new SyncRecipeToSearch($recipe->loadForSearch(), 'insert'));
        });
    }
}
