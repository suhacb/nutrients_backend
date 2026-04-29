<?php

namespace App\Http\Controllers;

use App\Exceptions\BrandHasIngredientsException;
use App\Http\Requests\BrandRequest;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;

class BrandsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Brand::paginate(25), 200);
    }

    public function show(Brand $brand): JsonResponse
    {
        return response()->json($brand->loadCount('ingredients'), 200);
    }

    public function store(BrandRequest $request): JsonResponse
    {
        $brand = Brand::create($request->validated());
        return response()->json($brand, 201);
    }

    public function update(BrandRequest $request, Brand $brand): JsonResponse
    {
        $brand->update($request->validated());
        return response()->json($brand->fresh(), 200);
    }

    public function delete(Brand $brand): JsonResponse
    {
        $brand->delete();
        return response()->json(null, 204);
    }
}
