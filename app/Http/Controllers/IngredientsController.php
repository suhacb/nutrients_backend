<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Http\Requests\IngredientRequest;
use App\Http\Requests\ResourceSearchRequest;
use App\Http\Resources\IngredientResource;
use App\Services\Search\SearchServiceContract;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class IngredientsController extends Controller
{
    public function __construct(private SearchServiceContract $search) {}

    public function index(): JsonResponse
    {
        return response()->json(Ingredient::paginate(25), 200);
    }

    public function show(Ingredient $ingredient): JsonResponse
    {
        $document = $this->search->get('ingredients', $ingredient->id);

        if ($document !== null) {
            return response()->json($document, 200);
        }

        return response()->json(new IngredientResource($ingredient->loadForSearch()), 200);
    }

    public function store(IngredientRequest $request): JsonResponse
    {
        $ingredient = Ingredient::create($request->validated());
        return response()->json($ingredient, 201);
    }

    public function update(IngredientRequest $request, Ingredient $ingredient): JsonResponse
    {
        $ingredient->update($request->validated());
        return response()->json($ingredient->fresh(), 200);
    }

    public function delete(Ingredient $ingredient): JsonResponse
    {
        $ingredient->delete();
        return response()->json(null, 204);
    }

    public function search(ResourceSearchRequest $request): JsonResponse
    {
        $result = $this->search->search('ingredients', $request->input('query'), 25, $request->page());
        return response()->json($result->toArray(), 200);
    }
}
