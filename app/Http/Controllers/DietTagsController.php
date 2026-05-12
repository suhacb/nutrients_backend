<?php

namespace App\Http\Controllers;

use App\Http\Requests\DietTagRequest;
use App\Http\Resources\DietTagResource;
use App\Models\DietTag;
use Illuminate\Http\JsonResponse;

class DietTagsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(DietTag::paginate(25), 200);
    }

    public function show(DietTag $dietTag): JsonResponse
    {
        return response()->json(new DietTagResource($dietTag), 200);
    }

    public function store(DietTagRequest $request): JsonResponse
    {
        $tag = DietTag::create($request->validated());
        return response()->json(new DietTagResource($tag), 201);
    }

    public function update(DietTagRequest $request, DietTag $dietTag): JsonResponse
    {
        $dietTag->update($request->validated());
        return response()->json(new DietTagResource($dietTag->fresh()), 200);
    }

    public function delete(DietTag $dietTag): JsonResponse
    {
        $dietTag->delete();
        return response()->json(null, 204);
    }
}
