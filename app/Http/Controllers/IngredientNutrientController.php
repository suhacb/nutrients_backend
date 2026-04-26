<?php

namespace App\Http\Controllers;

use App\Http\Requests\IngredientNutrientRequest;
use App\Models\Ingredient;
use App\Models\Nutrient;
use Illuminate\Http\JsonResponse;

class IngredientNutrientController extends Controller
{
    public function index(Ingredient $ingredient): JsonResponse
    {
        return response()->json($ingredient->nutrients()->get(), 200);
    }

    public function attach(IngredientNutrientRequest $request, Ingredient $ingredient): JsonResponse
    {
        $pivot = array_filter(
            $request->only(['amount', 'amount_unit_id']),
            fn($v) => $v !== null,
        );

        $ingredient->nutrients()->syncWithoutDetaching([
            $request->integer('nutrient_id') => $pivot,
        ]);

        return response()->json($ingredient->nutrients()->get(), 200);
    }

    public function updatePivot(IngredientNutrientRequest $request, Ingredient $ingredient, Nutrient $nutrient): JsonResponse
    {
        $ingredient->nutrients()->updateExistingPivot($nutrient->id, $request->validated());

        return response()->json($ingredient->nutrients()->get(), 200);
    }

    public function detach(Ingredient $ingredient, Nutrient $nutrient): JsonResponse
    {
        $ingredient->nutrients()->detach($nutrient->id);

        return response()->json(null, 204);
    }

    public function detachAll(IngredientNutrientRequest $request, Ingredient $ingredient): JsonResponse
    {
        $ids = $request->input('nutrient_ids');

        $ingredient->nutrients()->detach($ids);

        return response()->json(null, 204);
    }
}
