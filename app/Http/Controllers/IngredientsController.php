<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Http\Requests\IngredientRequest;
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
        return response()->json($ingredient->loadForSearch(), 200);
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
    
    public function search(): JsonResponse
    {
        return response()->json([], 200);
    }
}
