<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecipeIngredientRequest;
use App\Jobs\SyncRecipeToSearch;
use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;

class RecipeIngredientController extends Controller
{
    public function index(Recipe $recipe): JsonResponse
    {
        return response()->json($recipe->ingredients()->get(), 200);
    }

    public function attach(RecipeIngredientRequest $request, Recipe $recipe): JsonResponse
    {
        $recipe->ingredients()->syncWithoutDetaching([
            $request->integer('ingredient_id') => [
                'amount'  => $request->input('amount'),
                'unit_id' => $request->integer('unit_id'),
            ],
        ]);

        SyncRecipeToSearch::dispatch($recipe, 'update')->onQueue('recipes');

        return response()->json($recipe->ingredients()->get(), 200);
    }

    public function updatePivot(RecipeIngredientRequest $request, Recipe $recipe, Ingredient $ingredient): JsonResponse
    {
        $recipe->ingredients()->updateExistingPivot($ingredient->id, $request->validated());

        SyncRecipeToSearch::dispatch($recipe, 'update')->onQueue('recipes');

        return response()->json($recipe->ingredients()->get(), 200);
    }

    public function detach(Recipe $recipe, Ingredient $ingredient): JsonResponse
    {
        $recipe->ingredients()->detach($ingredient->id);

        SyncRecipeToSearch::dispatch($recipe, 'update')->onQueue('recipes');

        return response()->json(null, 204);
    }

    public function detachAll(Recipe $recipe): JsonResponse
    {
        $recipe->ingredients()->detach();

        SyncRecipeToSearch::dispatch($recipe, 'update')->onQueue('recipes');

        return response()->json(null, 204);
    }
}
