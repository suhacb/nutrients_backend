<?php

namespace App\Http\Controllers;

use App\Http\Requests\SourceRequest;
use App\Models\Source;
use Illuminate\Http\JsonResponse;

class SourcesController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Source::paginate(25), 200);
    }

    public function show(Source $source): JsonResponse
    {
        return response()->json($source, 200);
    }

    public function store(SourceRequest $request): JsonResponse
    {
        $source = Source::create($request->validated());
        return response()->json($source, 201);
    }

    public function update(SourceRequest $request, Source $source): JsonResponse
    {
        $source->update($request->validated());
        return response()->json($source->fresh(), 200);
    }

    public function delete(Source $source): JsonResponse
    {
        $source->delete();
        return response()->json(null, 204);
    }
}
