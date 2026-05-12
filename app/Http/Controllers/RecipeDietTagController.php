<?php

namespace App\Http\Controllers;

use App\Jobs\SyncRecipeToSearch;
use App\Models\DietTag;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecipeDietTagController extends Controller
{
    public function attach(Request $request, Recipe $recipe): JsonResponse
    {
        $request->validate([
            'diet_tag_id' => ['required', 'integer', 'exists:diet_tags,id'],
        ]);

        $recipe->dietTags()->syncWithoutDetaching([$request->integer('diet_tag_id')]);

        SyncRecipeToSearch::dispatch($recipe, 'update')->onQueue('recipes');

        return response()->json($recipe->dietTags()->get(), 200);
    }

    public function detach(Recipe $recipe, DietTag $dietTag): JsonResponse
    {
        $recipe->dietTags()->detach($dietTag->id);

        SyncRecipeToSearch::dispatch($recipe, 'update')->onQueue('recipes');

        return response()->json(null, 204);
    }

    public function detachAll(Recipe $recipe): JsonResponse
    {
        $recipe->dietTags()->detach();

        SyncRecipeToSearch::dispatch($recipe, 'update')->onQueue('recipes');

        return response()->json(null, 204);
    }
}
