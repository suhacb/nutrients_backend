<?php

namespace App\Http\Controllers;

use App\Http\Requests\DietTagRequest;
use App\Http\Resources\DietTagResource;
use App\Models\DietTag;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class DietTagsController extends Controller
{
    #[OA\Get(
        path: '/api/diet-tags',
        summary: 'List diet tags (paginated)',
        security: [['frontend' => []]],
        tags: ['Diet Tags'],
        parameters: [new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of diet tags', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/DietTag')),
                    new OA\Property(property: 'current_page', type: 'integer', example: 1),
                    new OA\Property(property: 'total', type: 'integer', example: 50),
                    new OA\Property(property: 'per_page', type: 'integer', example: 25),
                    new OA\Property(property: 'last_page', type: 'integer', example: 2),
                ]
            )),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function index(): JsonResponse
    {
        return response()->json(DietTag::paginate(25), 200);
    }

    #[OA\Get(
        path: '/api/diet-tags/{dietTag}',
        summary: 'Get a single diet tag',
        security: [['frontend' => []]],
        tags: ['Diet Tags'],
        parameters: [new OA\Parameter(name: 'dietTag', in: 'path', required: true, description: 'Diet tag ID', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Diet tag resource', content: new OA\JsonContent(ref: '#/components/schemas/DietTag')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(DietTag $dietTag): JsonResponse
    {
        return response()->json(new DietTagResource($dietTag), 200);
    }

    #[OA\Post(
        path: '/api/diet-tags',
        summary: 'Create a new diet tag',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name',        type: 'string', example: 'Ketogenic'),
                    new OA\Property(property: 'slug',        type: 'string', nullable: true, example: 'ketogenic'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'High fat, very low carbohydrate diet.'),
                ]
            )
        ),
        tags: ['Diet Tags'],
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/DietTag')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(DietTagRequest $request): JsonResponse
    {
        $tag = DietTag::create($request->validated());
        return response()->json(new DietTagResource($tag), 201);
    }

    #[OA\Put(
        path: '/api/diet-tags/{dietTag}',
        summary: 'Update a diet tag',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name',        type: 'string', example: 'Ketogenic'),
                    new OA\Property(property: 'slug',        type: 'string', nullable: true, example: 'ketogenic'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'High fat, very low carbohydrate diet.'),
                ]
            )
        ),
        tags: ['Diet Tags'],
        parameters: [new OA\Parameter(name: 'dietTag', in: 'path', required: true, description: 'Diet tag ID', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/DietTag')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(DietTagRequest $request, DietTag $dietTag): JsonResponse
    {
        $dietTag->update($request->validated());
        return response()->json(new DietTagResource($dietTag->fresh()), 200);
    }

    #[OA\Delete(
        path: '/api/diet-tags/{dietTag}',
        summary: 'Delete a diet tag',
        security: [['frontend' => []]],
        tags: ['Diet Tags'],
        parameters: [new OA\Parameter(name: 'dietTag', in: 'path', required: true, description: 'Diet tag ID', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function delete(DietTag $dietTag): JsonResponse
    {
        $dietTag->delete();
        return response()->json(null, 204);
    }
}
