<?php

namespace App\Http\Controllers;

use App\Http\Requests\NutrientRequest;
use App\Models\Nutrient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NutrientsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Nutrient::get());
    }

    public function show(Nutrient $nutrient): JsonResponse
    {
        return response()->json(false);
    }

    public function store(NutrientRequest $request): JsonResponse
    {
        return response()->json(false);
    }

    public function update(NutrientRequest $request, Nutrient $nutrient): JsonResponse
    {
        return response()->json(false);
    }

    public function delete(Nutrient $nutrient): JsonResponse
    {
        return response()->json(false);
    }
}
