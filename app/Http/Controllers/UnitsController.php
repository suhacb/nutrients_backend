<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\UnitRequest;
use OpenApi\Attributes as OA;

class UnitsController extends Controller
{
    #[OA\Get(
        path: '/api/units',
        summary: 'List units (paginated)',
        security: [['frontend' => []]],
        tags: ['Units'],
        parameters: [new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of units', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Unit')),
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
        return response()->json(Unit::paginate(25), 200);
    }

    #[OA\Get(
        path: '/api/units/{unit}',
        summary: 'Get a single unit with base and derived units',
        security: [['frontend' => []]],
        tags: ['Units'],
        parameters: [new OA\Parameter(name: 'unit', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Unit resource', content: new OA\JsonContent(ref: '#/components/schemas/Unit')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Unit $unit): JsonResponse
    {
        return response()->json($unit->load(['baseUnit', 'derivedUnits']), 200);
    }

    #[OA\Post(
        path: '/api/units',
        summary: 'Create a new unit',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'abbreviation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Gram'),
                    new OA\Property(property: 'abbreviation', type: 'string', example: 'g'),
                    new OA\Property(property: 'type', type: 'string', nullable: true, example: 'mass'),
                    new OA\Property(property: 'to_base_factor', type: 'number', format: 'float', nullable: true, example: 1.0),
                    new OA\Property(property: 'base_unit_id', type: 'integer', nullable: true),
                ]
            )
        ),
        tags: ['Units'],
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/Unit')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(UnitRequest $request): JsonResponse
    {
        $unit = Unit::create($request->validated());
        return response()->json($unit, 201);
    }

    #[OA\Put(
        path: '/api/units/{unit}',
        summary: 'Update a unit',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'abbreviation', type: 'string'),
                    new OA\Property(property: 'type', type: 'string', nullable: true),
                    new OA\Property(property: 'to_base_factor', type: 'number', format: 'float', nullable: true),
                    new OA\Property(property: 'base_unit_id', type: 'integer', nullable: true),
                ]
            )
        ),
        tags: ['Units'],
        parameters: [new OA\Parameter(name: 'unit', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/Unit')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UnitRequest $request, Unit $unit): JsonResponse
    {
        $unit->update($request->validated());
        return response()->json($unit->fresh(), 200);
    }

    #[OA\Delete(
        path: '/api/units/{unit}',
        summary: 'Delete a unit',
        security: [['frontend' => []]],
        tags: ['Units'],
        parameters: [new OA\Parameter(name: 'unit', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function delete(Unit $unit): JsonResponse
    {
        $unit->delete();
        return response()->json(null, 204);
    }
}
