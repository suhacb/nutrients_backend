<?php

namespace App\Http\Controllers;

use App\Models\Nutrient;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\NutrientRequest;
use App\Exceptions\NutrientAttachedException;

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

    public function delete(Nutrient $nutrient): JsonResponse
    {
        try {
            $nutrient->delete();

            // Return a JSON response with 204 (No Content) status
            return response()->json(null, 204);

        } catch (NutrientAttachedException $e) {

            // Return JSON with error message and appropriate status
            return response()->json([
                'message' => $e->getMessage()
            ], $e->status ?? 409);
        }
    }
}
