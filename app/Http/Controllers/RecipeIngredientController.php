<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecipeIngredientRequest;
use App\Jobs\SyncRecipeToSearch;
use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class RecipeIngredientController extends Controller
{
    #[OA\Get(
        path: '/api/recipes/{recipe}/ingredients',
        summary: 'List ingredients attached to a recipe',
        security: [['frontend' => []]],
        tags: ['Recipe Ingredients'],
        parameters: [new OA\Parameter(name: 'recipe', in: 'path', required: true, description: 'Recipe ID', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'List of ingredients with pivot data', content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'object'))),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Recipe not found'),
        ]
    )]
    public function index(Recipe $recipe): JsonResponse
    {
        return response()->json($recipe->ingredients()->get(), 200);
    }

    #[OA\Post(
        path: '/api/recipes/{recipe}/ingredients',
        summary: 'Attach an ingredient to a recipe',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ingredient_id', 'amount', 'unit_id'],
                properties: [
                    new OA\Property(property: 'ingredient_id', type: 'integer', example: 1),
                    new OA\Property(property: 'amount',        type: 'number',  format: 'float', example: 200.0),
                    new OA\Property(property: 'unit_id',       type: 'integer', example: 1),
                ]
            )
        ),
        tags: ['Recipe Ingredients'],
        parameters: [new OA\Parameter(name: 'recipe', in: 'path', required: true, description: 'Recipe ID', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Updated ingredient list', content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'object'))),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Recipe not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function attach(RecipeIngredientRequest $request, Recipe $recipe): JsonResponse
    {
        $recipe->ingredients()->syncWithoutDetaching([
            $request->integer('ingredient_id') => [
                'amount'  => $request->input('amount'),
                'unit_id' => $request->integer('unit_id'),
            ],
        ]);

        SyncRecipeToSearch::dispatch($recipe, 'update')->onQueue('recipes');

        return response()->json($recipe->ingredients()->get(), 200);
    }

    #[OA\Put(
        path: '/api/recipes/{recipe}/ingredients/{ingredient}',
        summary: 'Update the pivot data for an attached ingredient',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'amount',  type: 'number',  format: 'float', example: 150.0),
                    new OA\Property(property: 'unit_id', type: 'integer', example: 1),
                ]
            )
        ),
        tags: ['Recipe Ingredients'],
        parameters: [
            new OA\Parameter(name: 'recipe',     in: 'path', required: true, description: 'Recipe ID',     schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'ingredient', in: 'path', required: true, description: 'Ingredient ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Updated ingredient list', content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'object'))),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updatePivot(RecipeIngredientRequest $request, Recipe $recipe, Ingredient $ingredient): JsonResponse
    {
        $recipe->ingredients()->updateExistingPivot($ingredient->id, $request->validated());

        SyncRecipeToSearch::dispatch($recipe, 'update')->onQueue('recipes');

        return response()->json($recipe->ingredients()->get(), 200);
    }

    #[OA\Delete(
        path: '/api/recipes/{recipe}/ingredients/{ingredient}',
        summary: 'Remove a single ingredient from a recipe',
        security: [['frontend' => []]],
        tags: ['Recipe Ingredients'],
        parameters: [
            new OA\Parameter(name: 'recipe',     in: 'path', required: true, description: 'Recipe ID',     schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'ingredient', in: 'path', required: true, description: 'Ingredient ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Detached'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function detach(Recipe $recipe, Ingredient $ingredient): JsonResponse
    {
        $recipe->ingredients()->detach($ingredient->id);

        SyncRecipeToSearch::dispatch($recipe, 'update')->onQueue('recipes');

        return response()->json(null, 204);
    }

    #[OA\Delete(
        path: '/api/recipes/{recipe}/ingredients',
        summary: 'Remove all ingredients from a recipe',
        security: [['frontend' => []]],
        tags: ['Recipe Ingredients'],
        parameters: [new OA\Parameter(name: 'recipe', in: 'path', required: true, description: 'Recipe ID', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'All detached'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Recipe not found'),
        ]
    )]
    public function detachAll(Recipe $recipe): JsonResponse
    {
        $recipe->ingredients()->detach();

        SyncRecipeToSearch::dispatch($recipe, 'update')->onQueue('recipes');

        return response()->json(null, 204);
    }
}
