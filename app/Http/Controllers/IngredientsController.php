<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Http\Requests\IngredientRequest;
use App\Services\Search\SearchServiceContract;
use Symfony\Component\HttpFoundation\Response;

class IngredientsController extends Controller
{
    public function __construct(private SearchServiceContract $search) {}
    
    public function index(): Response
    {
        return response()->json(Ingredient::paginate(25), 200);
    }
    
    public function show(Ingredient $ingredient): Response
    {
        return response()->json($ingredient, 200);
    }
    
    public function store(IngredientRequest $request): Response
    {
        $ingredient = Ingredient::create($request->validated());
        return response()->json($ingredient, 201);
    }
    
    public function update(IngredientRequest $request, Ingredient $ingredient): Response
    {
        $ingredient->update($request->validated());
        return response()->json($ingredient->fresh(), 200);
    }
    
    public function delete(Ingredient $ingredient): Response
    {
        $ingredient->delete();
        return response()->noContent();
    }
    
    public function search(): Response
    {
        return response()->json([], 200);
    }
}
