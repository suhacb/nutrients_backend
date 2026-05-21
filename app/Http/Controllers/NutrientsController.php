<?php

namespace App\Http\Controllers;

use App\Models\Nutrient;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\NutrientRequest;
use App\Http\Requests\ResourceSearchRequest;
use App\Http\Resources\NutrientResource;
use App\Services\Search\SearchServiceContract;
use OpenApi\Attributes as OA;

class NutrientsController extends Controller
{
    public function __construct(private SearchServiceContract $search) {}

    #[OA\Get(
        path: '/api/nutrients',
        summary: 'List nutrients (paginated)',
        security: [['frontend' => []]],
        tags: ['Nutrients'],
        parameters: [new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of nutrients', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Nutrient')),
                    new OA\Property(property: 'current_page', type: 'integer', example: 1),
                    new OA\Property(property: 'total', type: 'integer', example: 100),
                    new OA\Property(property: 'per_page', type: 'integer', example: 25),
                    new OA\Property(property: 'last_page', type: 'integer', example: 4),
                ]
            )),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function index(): JsonResponse
    {
        return response()->json(Nutrient::paginate(25), 200);
    }

    #[OA\Get(
        path: '/api/nutrients/{nutrient}',
        summary: 'Get a single nutrient with all relationships',
        security: [['frontend' => []]],
        tags: ['Nutrients'],
        parameters: [new OA\Parameter(name: 'nutrient', in: 'path', required: true, description: 'Nutrient ID', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Nutrient resource', content: new OA\JsonContent(ref: '#/components/schemas/Nutrient')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Nutrient $nutrient): JsonResponse
    {
        return response()->json(new NutrientResource($nutrient->loadForSearch()), 200);
    }

    #[OA\Post(
        path: '/api/nutrients',
        summary: 'Create a new nutrient',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['source_id', 'name'],
                properties: [
                    new OA\Property(property: 'source_id', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'Vitamin C'),
                    new OA\Property(property: 'external_id', type: 'string', nullable: true, example: '1004'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'parent_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'slug', type: 'string', nullable: true, example: 'vitamin-c'),
                    new OA\Property(property: 'canonical_unit_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'iu_to_canonical_factor', type: 'number', format: 'float', nullable: true),
                    new OA\Property(property: 'is_label_standard', type: 'boolean', example: true),
                    new OA\Property(property: 'display_order', type: 'integer', nullable: true),
                ]
            )
        ),
        tags: ['Nutrients'],
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/Nutrient')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(NutrientRequest $request): JsonResponse
    {
        $nutrient = Nutrient::create($request->validated());
        return response()->json($nutrient, 201);
    }

    #[OA\Put(
        path: '/api/nutrients/{nutrient}',
        summary: 'Update a nutrient',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'source_id', type: 'integer'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'external_id', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'parent_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'slug', type: 'string', nullable: true),
                    new OA\Property(property: 'canonical_unit_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'iu_to_canonical_factor', type: 'number', format: 'float', nullable: true),
                    new OA\Property(property: 'is_label_standard', type: 'boolean'),
                    new OA\Property(property: 'display_order', type: 'integer', nullable: true),
                ]
            )
        ),
        tags: ['Nutrients'],
        parameters: [new OA\Parameter(name: 'nutrient', in: 'path', required: true, description: 'Nutrient ID', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/Nutrient')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(NutrientRequest $request, Nutrient $nutrient): JsonResponse
    {
        $nutrient->update($request->validated());
        return response()->json($nutrient->fresh(), 200);
    }

    #[OA\Delete(
        path: '/api/nutrients/{nutrient}',
        summary: 'Soft-delete a nutrient',
        security: [['frontend' => []]],
        tags: ['Nutrients'],
        parameters: [new OA\Parameter(name: 'nutrient', in: 'path', required: true, description: 'Nutrient ID', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function delete(Nutrient $nutrient): JsonResponse
    {
        $nutrient->delete();
        return response()->json(null, 204);
    }

    #[OA\Post(
        path: '/api/nutrients/search',
        summary: 'Full-text search nutrients',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['query'],
                properties: [
                    new OA\Property(property: 'query', type: 'string', example: 'vitamin c'),
                    new OA\Property(property: 'page', type: 'integer', example: 1),
                ]
            )
        ),
        tags: ['Nutrients'],
        responses: [
            new OA\Response(response: 200, description: 'Search results', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'hits', type: 'array', items: new OA\Items(ref: '#/components/schemas/Nutrient')),
                    new OA\Property(property: 'total', type: 'integer', example: 5),
                    new OA\Property(property: 'page', type: 'integer', example: 1),
                ]
            )),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function search(ResourceSearchRequest $request): JsonResponse
    {
        $result = $this->search->search(config('zinc.indices.nutrients'), $request->input('query'), 25, $request->page());
        return response()->json($result->toArray(), 200);
    }
}
