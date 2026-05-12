<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecipeRequest;
use App\Http\Requests\ResourceSearchRequest;
use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use App\Services\Search\SearchServiceContract;
use Illuminate\Http\JsonResponse;

class RecipesController extends Controller
{
    public function __construct(private SearchServiceContract $search) {}

    public function index(): JsonResponse
    {
        return response()->json(Recipe::paginate(25), 200);
    }

    public function show(Recipe $recipe): JsonResponse
    {
        $document = $this->search->get(config('zinc.indices.recipes'), $recipe->id);

        if ($document !== null) {
            return response()->json($document, 200);
        }

        return response()->json(new RecipeResource($recipe->loadForSearch()), 200);
    }

    public function store(RecipeRequest $request): JsonResponse
    {
        $recipe = Recipe::create($request->validated());
        return response()->json(new RecipeResource($recipe), 201);
    }

    public function update(RecipeRequest $request, Recipe $recipe): JsonResponse
    {
        $recipe->update($request->validated());
        return response()->json(new RecipeResource($recipe->fresh()), 200);
    }

    public function delete(Recipe $recipe): JsonResponse
    {
        $recipe->delete();
        return response()->json(null, 204);
    }

    public function search(ResourceSearchRequest $request): JsonResponse
    {
        $result = $this->search->search(
            config('zinc.indices.recipes'),
            $request->input('query'),
            25,
            $request->page()
        );

        return response()->json($result->toArray(), 200);
    }

    public function nutrientProfile(Recipe $recipe): JsonResponse
    {
        return response()->json([
            'total'       => $recipe->computeNutrientProfile(),
            'per_portion' => $recipe->computeNutrientProfilePerPortion(),
            'portions'    => $recipe->portions,
        ], 200);
    }
}
