<?php

namespace App\Http\Controllers;

use App\Http\Requests\NutrientTagRequest;
use App\Models\Nutrient;
use App\Models\NutrientTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class NutrientTagsController extends Controller
{
    #[OA\Get(
        path: '/api/nutrient-tags',
        summary: 'List nutrient tags (paginated)',
        security: [['frontend' => []]],
        tags: ['NutrientTags'],
        parameters: [new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of nutrient tags', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/NutrientTag')),
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
        return response()->json(NutrientTag::paginate(25), 200);
    }

    #[OA\Get(
        path: '/api/nutrient-tags/{nutrientTag}',
        summary: 'Get a single nutrient tag',
        security: [['frontend' => []]],
        tags: ['NutrientTags'],
        parameters: [new OA\Parameter(name: 'nutrientTag', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'NutrientTag resource', content: new OA\JsonContent(ref: '#/components/schemas/NutrientTag')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(NutrientTag $nutrientTag): JsonResponse
    {
        return response()->json($nutrientTag, 200);
    }

    #[OA\Post(
        path: '/api/nutrient-tags',
        summary: 'Create a new nutrient tag',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'slug'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Fat-soluble'),
                    new OA\Property(property: 'slug', type: 'string', example: 'fat-soluble'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                ]
            )
        ),
        tags: ['NutrientTags'],
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/NutrientTag')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(NutrientTagRequest $request): JsonResponse
    {
        $tag = NutrientTag::create($request->validated());
        return response()->json($tag, 201);
    }

    #[OA\Put(
        path: '/api/nutrient-tags/{nutrientTag}',
        summary: 'Update a nutrient tag',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                ]
            )
        ),
        tags: ['NutrientTags'],
        parameters: [new OA\Parameter(name: 'nutrientTag', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/NutrientTag')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(NutrientTagRequest $request, NutrientTag $nutrientTag): JsonResponse
    {
        $nutrientTag->update($request->validated());
        return response()->json($nutrientTag->fresh(), 200);
    }

    #[OA\Delete(
        path: '/api/nutrient-tags/{nutrientTag}',
        summary: 'Delete a nutrient tag',
        security: [['frontend' => []]],
        tags: ['NutrientTags'],
        parameters: [new OA\Parameter(name: 'nutrientTag', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function delete(NutrientTag $nutrientTag): JsonResponse
    {
        $nutrientTag->delete();
        return response()->json(null, 204);
    }

    #[OA\Post(
        path: '/api/nutrients/{nutrient}/tags',
        summary: 'Attach a tag to a nutrient',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['tag_id'],
                properties: [new OA\Property(property: 'tag_id', type: 'integer', example: 1)]
            )
        ),
        tags: ['NutrientTags'],
        parameters: [new OA\Parameter(name: 'nutrient', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Updated tag list', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/NutrientTag'))),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function attach(Request $request, Nutrient $nutrient): JsonResponse
    {
        $request->validate([
            'tag_id' => ['required', 'integer', 'exists:nutrient_tags,id'],
        ]);

        $nutrient->tags()->syncWithoutDetaching([$request->integer('tag_id')]);

        return response()->json($nutrient->tags()->get(), 200);
    }

    #[OA\Delete(
        path: '/api/nutrients/{nutrient}/tags/{tag}',
        summary: 'Detach a specific tag from a nutrient',
        security: [['frontend' => []]],
        tags: ['NutrientTags'],
        parameters: [
            new OA\Parameter(name: 'nutrient', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'tag', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Detached'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function detach(Nutrient $nutrient, NutrientTag $tag): JsonResponse
    {
        $nutrient->tags()->detach($tag->id);

        return response()->json(null, 204);
    }

    #[OA\Delete(
        path: '/api/nutrients/{nutrient}/tags',
        summary: 'Detach all tags from a nutrient',
        security: [['frontend' => []]],
        tags: ['NutrientTags'],
        parameters: [new OA\Parameter(name: 'nutrient', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'All tags detached'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function detachAll(Nutrient $nutrient): JsonResponse
    {
        $nutrient->tags()->detach();

        return response()->json(null, 204);
    }
}
