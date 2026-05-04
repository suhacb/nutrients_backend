<?php

namespace App\Http\Controllers;

use App\Http\Requests\SourceRequest;
use App\Models\Source;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class SourcesController extends Controller
{
    #[OA\Get(
        path: '/api/sources',
        summary: 'List sources (paginated)',
        security: [['frontend' => []]],
        tags: ['Sources'],
        parameters: [new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of sources', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Source')),
                    new OA\Property(property: 'current_page', type: 'integer'),
                    new OA\Property(property: 'total', type: 'integer'),
                    new OA\Property(property: 'per_page', type: 'integer'),
                    new OA\Property(property: 'last_page', type: 'integer'),
                ]
            )),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function index(): JsonResponse
    {
        return response()->json(Source::paginate(25), 200);
    }

    #[OA\Get(
        path: '/api/sources/{source}',
        summary: 'Get a single source',
        security: [['frontend' => []]],
        tags: ['Sources'],
        parameters: [new OA\Parameter(name: 'source', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Source resource', content: new OA\JsonContent(ref: '#/components/schemas/Source')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Source $source): JsonResponse
    {
        return response()->json($source, 200);
    }

    #[OA\Post(
        path: '/api/sources',
        summary: 'Create a new source',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'slug'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'USDA FoodData Central'),
                    new OA\Property(property: 'slug', type: 'string', example: 'usda-fooddata-central'),
                    new OA\Property(property: 'url', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                ]
            )
        ),
        tags: ['Sources'],
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/Source')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(SourceRequest $request): JsonResponse
    {
        $source = Source::create($request->validated());
        return response()->json($source, 201);
    }

    #[OA\Put(
        path: '/api/sources/{source}',
        summary: 'Update a source',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string'),
                    new OA\Property(property: 'url', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                ]
            )
        ),
        tags: ['Sources'],
        parameters: [new OA\Parameter(name: 'source', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/Source')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(SourceRequest $request, Source $source): JsonResponse
    {
        $source->update($request->validated());
        return response()->json($source->fresh(), 200);
    }

    #[OA\Delete(
        path: '/api/sources/{source}',
        summary: 'Delete a source',
        security: [['frontend' => []]],
        tags: ['Sources'],
        parameters: [new OA\Parameter(name: 'source', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function delete(Source $source): JsonResponse
    {
        $source->delete();
        return response()->json(null, 204);
    }
}
