<?php

namespace App\Http\Controllers;

use App\Models\Nutrient;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\NutrientRequest;
use App\Http\Requests\ResourceSearchRequest;
use App\Services\Search\SearchServiceContract;

class NutrientsController extends Controller
{
    public function __construct(private SearchServiceContract $search) {}

    public function index(): JsonResponse
    {
        return response()->json(Nutrient::paginate(25), 200);
    }

    public function show(Nutrient $nutrient): JsonResponse
    {
        $document = $this->search->get('nutrients', $nutrient->id);

        if ($document !== null) {
            return response()->json($document, 200);
        }

        return response()->json($nutrient->loadForSearch(), 200);
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
        $nutrient->delete();
        return response()->json(null, 204);
    }

    public function search(ResourceSearchRequest $request): JsonResponse
    {
        $result = $this->search->search('nutrients', $request->input('query'), 25, $request->page());
        return response()->json($result->toArray(), 200);
    }
}
