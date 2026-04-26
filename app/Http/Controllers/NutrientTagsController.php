<?php

namespace App\Http\Controllers;

use App\Http\Requests\NutrientTagRequest;
use App\Models\Nutrient;
use App\Models\NutrientTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NutrientTagsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(NutrientTag::paginate(25), 200);
    }

    public function show(NutrientTag $nutrientTag): JsonResponse
    {
        return response()->json($nutrientTag, 200);
    }

    public function store(NutrientTagRequest $request): JsonResponse
    {
        $tag = NutrientTag::create($request->validated());
        return response()->json($tag, 201);
    }

    public function update(NutrientTagRequest $request, NutrientTag $nutrientTag): JsonResponse
    {
        $nutrientTag->update($request->validated());
        return response()->json($nutrientTag->fresh(), 200);
    }

    public function delete(NutrientTag $nutrientTag): JsonResponse
    {
        $nutrientTag->delete();
        return response()->json(null, 204);
    }

    public function attach(Request $request, Nutrient $nutrient): JsonResponse
    {
        $request->validate([
            'tag_id' => ['required', 'integer', 'exists:nutrient_tags,id'],
        ]);

        $nutrient->tags()->syncWithoutDetaching([$request->integer('tag_id')]);

        return response()->json($nutrient->tags()->get(), 200);
    }

    public function detach(Nutrient $nutrient, NutrientTag $tag): JsonResponse
    {
        $nutrient->tags()->detach($tag->id);

        return response()->json(null, 204);
    }

    public function detachAll(Nutrient $nutrient): JsonResponse
    {
        $nutrient->tags()->detach();

        return response()->json(null, 204);
    }
}