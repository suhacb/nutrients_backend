<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Http\Requests\IngredientRequest;
use App\Http\Requests\ResourceSearchRequest;
use App\Http\Resources\IngredientResource;
use App\Services\Search\SearchServiceContract;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class IngredientsController extends Controller
{
    public function __construct(private SearchServiceContract $search) {}

    #[OA\Get(
        path: '/api/ingredients',
        summary: 'List ingredients (paginated)',
        security: [['frontend' => []]],
        tags: ['Ingredients'],
        parameters: [new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of ingredients', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Ingredient')),
                    new OA\Property(property: 'current_page', type: 'integer', example: 1),
                    new OA\Property(property: 'total', type: 'integer', example: 500),
                    new OA\Property(property: 'per_page', type: 'integer', example: 25),
                    new OA\Property(property: 'last_page', type: 'integer', example: 20),
                ]
            )),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function index(): JsonResponse
    {
        return response()->json(Ingredient::paginate(25), 200);
    }

    #[OA\Get(
        path: '/api/ingredients/{ingredient}',
        summary: 'Get a single ingredient with all relationships',
        security: [['frontend' => []]],
        tags: ['Ingredients'],
        parameters: [new OA\Parameter(name: 'ingredient', in: 'path', required: true, description: 'Ingredient ID', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Ingredient resource', content: new OA\JsonContent(ref: '#/components/schemas/Ingredient')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Ingredient $ingredient): JsonResponse
    {
        $document = $this->search->get('ingredients', $ingredient->id);

        if ($document !== null) {
            return response()->json($document, 200);
        }

        return response()->json(new IngredientResource($ingredient->loadForSearch()), 200);
    }

    #[OA\Post(
        path: '/api/ingredients',
        summary: 'Create a new ingredient',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['source', 'name', 'default_amount', 'default_amount_unit_id'],
                properties: [
                    new OA\Property(property: 'source', type: 'string', example: 'USDA'),
                    new OA\Property(property: 'name', type: 'string', example: 'Broccoli, raw'),
                    new OA\Property(property: 'external_id', type: 'string', nullable: true, example: '321360'),
                    new OA\Property(property: 'class', type: 'string', nullable: true),
                    new OA\Property(property: 'slug', type: 'string', nullable: true, example: 'broccoli-raw'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'default_amount', type: 'number', format: 'float', example: 100.0),
                    new OA\Property(property: 'default_amount_unit_id', type: 'integer', example: 1),
                    new OA\Property(property: 'brand_id', type: 'integer', nullable: true),
                ]
            )
        ),
        tags: ['Ingredients'],
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/Ingredient')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(IngredientRequest $request): JsonResponse
    {
        $ingredient = Ingredient::create($request->validated());
        return response()->json($ingredient, 201);
    }

    #[OA\Put(
        path: '/api/ingredients/{ingredient}',
        summary: 'Update an ingredient',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'source', type: 'string'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'external_id', type: 'string', nullable: true),
                    new OA\Property(property: 'class', type: 'string', nullable: true),
                    new OA\Property(property: 'slug', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'default_amount', type: 'number', format: 'float'),
                    new OA\Property(property: 'default_amount_unit_id', type: 'integer'),
                    new OA\Property(property: 'brand_id', type: 'integer', nullable: true),
                ]
            )
        ),
        tags: ['Ingredients'],
        parameters: [new OA\Parameter(name: 'ingredient', in: 'path', required: true, description: 'Ingredient ID', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/Ingredient')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(IngredientRequest $request, Ingredient $ingredient): JsonResponse
    {
        $ingredient->update($request->validated());
        return response()->json($ingredient->fresh(), 200);
    }

    #[OA\Delete(
        path: '/api/ingredients/{ingredient}',
        summary: 'Soft-delete an ingredient',
        security: [['frontend' => []]],
        tags: ['Ingredients'],
        parameters: [new OA\Parameter(name: 'ingredient', in: 'path', required: true, description: 'Ingredient ID', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function delete(Ingredient $ingredient): JsonResponse
    {
        $ingredient->delete();
        return response()->json(null, 204);
    }

    #[OA\Post(
        path: '/api/ingredients/search',
        summary: 'Full-text search ingredients',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['query'],
                properties: [
                    new OA\Property(property: 'query', type: 'string', example: 'broccoli'),
                    new OA\Property(property: 'page', type: 'integer', example: 1),
                ]
            )
        ),
        tags: ['Ingredients'],
        responses: [
            new OA\Response(response: 200, description: 'Search results', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'hits', type: 'array', items: new OA\Items(ref: '#/components/schemas/Ingredient')),
                    new OA\Property(property: 'total', type: 'integer', example: 3),
                    new OA\Property(property: 'page', type: 'integer', example: 1),
                ]
            )),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function search(ResourceSearchRequest $request): JsonResponse
    {
        $result = $this->search->search('ingredients', $request->input('query'), 25, $request->page());
        return response()->json($result->toArray(), 200);
    }
}
