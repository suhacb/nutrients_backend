<?php

namespace App\Http\Controllers;

use App\Http\Requests\NutrientRequest;
use App\Models\Nutrient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NutrientsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Nutrient::paginate(25), 200);
    }

    public function show(Nutrient $nutrient): JsonResponse
    {
        return response()->json($nutrient, 200);
    }

    public function store(NutrientRequest $request): JsonResponse
    {
        $nutrient = Nutrient::create($request->validated());
        return response()->json($nutrient, 201);
    }

    public function update(NutrientRequest $request, Nutrient $nutrient): JsonResponse
    {
        $nutrient->update($request->validated());
        return response()->json($nutrient->fresh(), 200);
    }

    public function delete(Nutrient $nutrient): Response
    {
        $nutrient->delete();
        return response()->noContent();
    }
}
