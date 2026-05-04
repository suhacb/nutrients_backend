<?php

namespace App\Http\Controllers;

use App\Exceptions\BrandHasIngredientsException;
use App\Http\Requests\BrandRequest;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class BrandsController extends Controller
{
    #[OA\Get(
        path: '/api/brands',
        summary: 'List brands (paginated)',
        security: [['frontend' => []]],
        tags: ['Brands'],
        parameters: [new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of brands', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Brand')),
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
        return response()->json(Brand::paginate(25), 200);
    }

    #[OA\Get(
        path: '/api/brands/{brand}',
        summary: 'Get a single brand',
        security: [['frontend' => []]],
        tags: ['Brands'],
        parameters: [new OA\Parameter(name: 'brand', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Brand resource', content: new OA\JsonContent(ref: '#/components/schemas/Brand')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Brand $brand): JsonResponse
    {
        return response()->json($brand->loadCount('ingredients'), 200);
    }

    #[OA\Post(
        path: '/api/brands',
        summary: 'Create a new brand',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Acme Foods'),
                    new OA\Property(property: 'slug', type: 'string', nullable: true),
                    new OA\Property(property: 'owner', type: 'string', nullable: true),
                    new OA\Property(property: 'country', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                ]
            )
        ),
        tags: ['Brands'],
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/Brand')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(BrandRequest $request): JsonResponse
    {
        $brand = Brand::create($request->validated());
        return response()->json($brand, 201);
    }

    #[OA\Put(
        path: '/api/brands/{brand}',
        summary: 'Update a brand',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string', nullable: true),
                    new OA\Property(property: 'owner', type: 'string', nullable: true),
                    new OA\Property(property: 'country', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                ]
            )
        ),
        tags: ['Brands'],
        parameters: [new OA\Parameter(name: 'brand', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/Brand')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(BrandRequest $request, Brand $brand): JsonResponse
    {
        $brand->update($request->validated());
        return response()->json($brand->fresh(), 200);
    }

    #[OA\Delete(
        path: '/api/brands/{brand}',
        summary: 'Delete a brand',
        security: [['frontend' => []]],
        tags: ['Brands'],
        parameters: [new OA\Parameter(name: 'brand', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function delete(Brand $brand): JsonResponse
    {
        $brand->delete();
        return response()->json(null, 204);
    }
}
