<?php

namespace App\Http\Controllers;

use App\Jobs\SyncRecipeToSearch;
use App\Models\DietTag;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class RecipeDietTagController extends Controller
{
    #[OA\Post(
        path: '/api/recipes/{recipe}/diet-tags',
        summary: 'Attach a diet tag to a recipe',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['diet_tag_id'],
                properties: [
                    new OA\Property(property: 'diet_tag_id', type: 'integer', example: 1),
                ]
            )
        ),
        tags: ['Recipe Diet Tags'],
        parameters: [new OA\Parameter(name: 'recipe', in: 'path', required: true, description: 'Recipe ID', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Updated diet tag list', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/DietTag'))),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Recipe not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function attach(Request $request, Recipe $recipe): JsonResponse
    {
        $request->validate([
            'diet_tag_id' => ['required', 'integer', 'exists:diet_tags,id'],
        ]);

        $recipe->dietTags()->syncWithoutDetaching([$request->integer('diet_tag_id')]);

        SyncRecipeToSearch::dispatch($recipe, 'update')->onQueue('recipes');

        return response()->json($recipe->dietTags()->get(), 200);
    }

    #[OA\Delete(
        path: '/api/recipes/{recipe}/diet-tags/{dietTag}',
        summary: 'Remove a single diet tag from a recipe',
        security: [['frontend' => []]],
        tags: ['Recipe Diet Tags'],
        parameters: [
            new OA\Parameter(name: 'recipe',  in: 'path', required: true, description: 'Recipe ID',   schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'dietTag', in: 'path', required: true, description: 'Diet tag ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Detached'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function detach(Recipe $recipe, DietTag $dietTag): JsonResponse
    {
        $recipe->dietTags()->detach($dietTag->id);

        SyncRecipeToSearch::dispatch($recipe, 'update')->onQueue('recipes');

        return response()->json(null, 204);
    }

    #[OA\Delete(
        path: '/api/recipes/{recipe}/diet-tags',
        summary: 'Remove all diet tags from a recipe',
        security: [['frontend' => []]],
        tags: ['Recipe Diet Tags'],
        parameters: [new OA\Parameter(name: 'recipe', in: 'path', required: true, description: 'Recipe ID', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'All detached'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Recipe not found'),
        ]
    )]
    public function detachAll(Recipe $recipe): JsonResponse
    {
        $recipe->dietTags()->detach();

        SyncRecipeToSearch::dispatch($recipe, 'update')->onQueue('recipes');

        return response()->json(null, 204);
    }
}
