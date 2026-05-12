<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecipeRequest;
use App\Http\Requests\ResourceSearchRequest;
use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use App\Services\Search\SearchServiceContract;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class RecipesController extends Controller
{
    public function __construct(private SearchServiceContract $search) {}

    #[OA\Get(
        path: '/api/recipes',
        summary: 'List recipes (paginated)',
        security: [['frontend' => []]],
        tags: ['Recipes'],
        parameters: [new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of recipes', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Recipe')),
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
        return response()->json(Recipe::paginate(25), 200);
    }

    #[OA\Get(
        path: '/api/recipes/{recipe}',
        summary: 'Get a single recipe with all relationships',
        security: [['frontend' => []]],
        tags: ['Recipes'],
        parameters: [new OA\Parameter(name: 'recipe', in: 'path', required: true, description: 'Recipe ID', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Recipe resource', content: new OA\JsonContent(ref: '#/components/schemas/Recipe')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Recipe $recipe): JsonResponse
    {
        $document = $this->search->get(config('zinc.indices.recipes'), $recipe->id);

        if ($document !== null) {
            return response()->json($document, 200);
        }

        return response()->json(new RecipeResource($recipe->loadForSearch()), 200);
    }

    #[OA\Post(
        path: '/api/recipes',
        summary: 'Create a new recipe',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'portions'],
                properties: [
                    new OA\Property(property: 'name',         type: 'string',  example: 'Pasta Bolognese'),
                    new OA\Property(property: 'slug',         type: 'string',  nullable: true, example: 'pasta-bolognese'),
                    new OA\Property(property: 'description',  type: 'string',  nullable: true, example: 'A classic Italian dish.'),
                    new OA\Property(property: 'instructions', type: 'string',  nullable: true, example: "## Method\n1. Cook pasta."),
                    new OA\Property(property: 'portions',     type: 'integer', example: 4),
                    new OA\Property(property: 'source_url',   type: 'string',  nullable: true, example: 'https://example.com/pasta'),
                ]
            )
        ),
        tags: ['Recipes'],
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/Recipe')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(RecipeRequest $request): JsonResponse
    {
        $recipe = Recipe::create($request->validated());
        return response()->json(new RecipeResource($recipe), 201);
    }

    #[OA\Put(
        path: '/api/recipes/{recipe}',
        summary: 'Update a recipe',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name',         type: 'string',  example: 'Pasta Bolognese'),
                    new OA\Property(property: 'slug',         type: 'string',  nullable: true, example: 'pasta-bolognese'),
                    new OA\Property(property: 'description',  type: 'string',  nullable: true, example: 'A classic Italian dish.'),
                    new OA\Property(property: 'instructions', type: 'string',  nullable: true, example: "## Method\n1. Cook pasta."),
                    new OA\Property(property: 'portions',     type: 'integer', example: 4),
                    new OA\Property(property: 'source_url',   type: 'string',  nullable: true, example: 'https://example.com/pasta'),
                ]
            )
        ),
        tags: ['Recipes'],
        parameters: [new OA\Parameter(name: 'recipe', in: 'path', required: true, description: 'Recipe ID', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/Recipe')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(RecipeRequest $request, Recipe $recipe): JsonResponse
    {
        $recipe->update($request->validated());
        return response()->json(new RecipeResource($recipe->fresh()), 200);
    }

    #[OA\Delete(
        path: '/api/recipes/{recipe}',
        summary: 'Soft-delete a recipe',
        security: [['frontend' => []]],
        tags: ['Recipes'],
        parameters: [new OA\Parameter(name: 'recipe', in: 'path', required: true, description: 'Recipe ID', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function delete(Recipe $recipe): JsonResponse
    {
        $recipe->delete();
        return response()->json(null, 204);
    }

    #[OA\Post(
        path: '/api/recipes/search',
        summary: 'Full-text search recipes',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['query'],
                properties: [
                    new OA\Property(property: 'query', type: 'string', example: 'pasta'),
                    new OA\Property(property: 'page',  type: 'integer', example: 1),
                ]
            )
        ),
        tags: ['Recipes'],
        responses: [
            new OA\Response(response: 200, description: 'Search results', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'results',      type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'total',        type: 'integer', example: 3),
                    new OA\Property(property: 'current_page', type: 'integer', example: 1),
                    new OA\Property(property: 'last_page',    type: 'integer', example: 1),
                    new OA\Property(property: 'per_page',     type: 'integer', example: 25),
                ]
            )),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function search(ResourceSearchRequest $request): JsonResponse
    {
        $result = $this->search->search(
            config('zinc.indices.recipes'),
            $request->input('query'),
            25,
            $request->page()
        );

        return response()->json($result->toArray(), 200);
    }

    #[OA\Get(
        path: '/api/recipes/{recipe}/nutrient-profile',
        summary: 'Get computed nutrient profile for a recipe',
        security: [['frontend' => []]],
        tags: ['Recipes'],
        parameters: [new OA\Parameter(name: 'recipe', in: 'path', required: true, description: 'Recipe ID', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Nutrient profile', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'total', type: 'array', items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'nutrient_id',   type: 'integer', example: 1),
                            new OA\Property(property: 'nutrient_name', type: 'string',  example: 'Protein'),
                            new OA\Property(property: 'amount',        type: 'number',  format: 'float', example: 20.0),
                            new OA\Property(property: 'unit_id',       type: 'integer', example: 1),
                            new OA\Property(property: 'unit',          type: 'string',  example: 'g'),
                        ]
                    )),
                    new OA\Property(property: 'per_portion', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'portions',    type: 'integer', example: 4),
                ]
            )),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function nutrientProfile(Recipe $recipe): JsonResponse
    {
        return response()->json([
            'total'       => $recipe->computeNutrientProfile(),
            'per_portion' => $recipe->computeNutrientProfilePerPortion(),
            'portions'    => $recipe->portions,
        ], 200);
    }
}
