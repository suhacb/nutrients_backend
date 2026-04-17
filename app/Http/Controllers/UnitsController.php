<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\UnitRequest;

class UnitsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Unit::paginate(25), 200);
    }

    public function show(Unit $unit): JsonResponse
    {
        return response()->json($unit->load(['baseUnit', 'derivedUnits']), 200);
    }

    public function store(UnitRequest $request): JsonResponse
    {
        $unit = Unit::create($request->validated());
        return response()->json($unit, 201);
    }

    public function update(UnitRequest $request, Unit $unit): JsonResponse
    {
        $unit->update($request->validated());
        return response()->json($unit->fresh(), 200);
    }

    public function delete(Unit $unit): JsonResponse
    {
        $unit->delete();
        return response()->json(null, 204);
    }
}
