<?php

namespace App\Http\Controllers;

use App\Http\Requests\IngredientNutrientRequest;
use App\Models\Ingredient;
use App\Models\Nutrient;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class IngredientNutrientController extends Controller
{
    #[OA\Get(
        path: '/api/ingredients/{ingredient}/nutrients',
        summary: 'List nutrients attached to an ingredient',
        security: [['frontend' => []]],
        tags: ['IngredientNutrients'],
        parameters: [new OA\Parameter(name: 'ingredient', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Nutrient list', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Nutrient'))),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function index(Ingredient $ingredient): JsonResponse
    {
        return response()->json($ingredient->nutrients()->get(), 200);
    }

    #[OA\Post(
        path: '/api/ingredients/{ingredient}/nutrients/attach',
        summary: 'Attach a nutrient to an ingredient',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nutrient_id'],
                properties: [
                    new OA\Property(property: 'nutrient_id', type: 'integer', example: 1),
                    new OA\Property(property: 'amount', type: 'number', format: 'float', nullable: true, example: 10.5),
                    new OA\Property(property: 'amount_unit_id', type: 'integer', nullable: true),
                ]
            )
        ),
        tags: ['IngredientNutrients'],
        parameters: [new OA\Parameter(name: 'ingredient', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Updated nutrient list', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Nutrient'))),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function attach(IngredientNutrientRequest $request, Ingredient $ingredient): JsonResponse
    {
        $pivot = array_filter(
            $request->only(['amount', 'amount_unit_id']),
            fn($v) => $v !== null,
        );

        $ingredient->nutrients()->syncWithoutDetaching([
            $request->integer('nutrient_id') => $pivot,
        ]);

        return response()->json($ingredient->nutrients()->get(), 200);
    }

    #[OA\Put(
        path: '/api/ingredients/{ingredient}/nutrients/{nutrient}',
        summary: 'Update pivot data for a nutrient on an ingredient',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'amount', type: 'number', format: 'float', nullable: true),
                    new OA\Property(property: 'amount_unit_id', type: 'integer', nullable: true),
                ]
            )
        ),
        tags: ['IngredientNutrients'],
        parameters: [
            new OA\Parameter(name: 'ingredient', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'nutrient', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Updated nutrient list', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Nutrient'))),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updatePivot(IngredientNutrientRequest $request, Ingredient $ingredient, Nutrient $nutrient): JsonResponse
    {
        $ingredient->nutrients()->updateExistingPivot($nutrient->id, $request->validated());

        return response()->json($ingredient->nutrients()->get(), 200);
    }

    #[OA\Delete(
        path: '/api/ingredients/{ingredient}/nutrients/{nutrient}',
        summary: 'Detach a nutrient from an ingredient',
        security: [['frontend' => []]],
        tags: ['IngredientNutrients'],
        parameters: [
            new OA\Parameter(name: 'ingredient', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'nutrient', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Detached'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function detach(Ingredient $ingredient, Nutrient $nutrient): JsonResponse
    {
        $ingredient->nutrients()->detach($nutrient->id);

        return response()->json(null, 204);
    }

    #[OA\Delete(
        path: '/api/ingredients/{ingredient}/nutrients',
        summary: 'Detach multiple nutrients from an ingredient',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nutrient_ids'],
                properties: [new OA\Property(property: 'nutrient_ids', type: 'array', items: new OA\Items(type: 'integer'))]
            )
        ),
        tags: ['IngredientNutrients'],
        parameters: [new OA\Parameter(name: 'ingredient', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Detached'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function detachAll(IngredientNutrientRequest $request, Ingredient $ingredient): JsonResponse
    {
        $ids = $request->input('nutrient_ids');

        $ingredient->nutrients()->detach($ids);

        return response()->json(null, 204);
    }
}
