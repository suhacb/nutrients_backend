<?php

namespace App\Http\Controllers;

use App\Http\Requests\NutrientMappingReviewRequest;
use App\Http\Resources\NutrientMappingReviewResource;
use App\Models\Nutrient;
use App\Models\NutrientMappingReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class NutrientMappingReviewsController extends Controller
{
    #[OA\Get(
        path: '/api/nutrient-mapping-reviews',
        summary: 'List nutrient mapping reviews',
        security: [['frontend' => []]],
        tags: ['NutrientMappingReviews'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'approved', 'rejected'], default: 'pending')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of reviews', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id',                  type: 'integer'),
                            new OA\Property(property: 'status',              type: 'string'),
                            new OA\Property(property: 'confidence',          type: 'integer'),
                            new OA\Property(property: 'decision_type',       type: 'string'),
                            new OA\Property(property: 'reasoning',           type: 'string'),
                            new OA\Property(property: 'resolved_at',         type: 'string', nullable: true),
                            new OA\Property(property: 'source_nutrient',     type: 'object', nullable: true),
                            new OA\Property(property: 'suggested_canonical', type: 'object', nullable: true),
                        ],
                        type: 'object'
                    )),
                ]
            )),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $status  = $request->query('status', 'pending');
        $reviews = NutrientMappingReview::where('status', $status)
            ->with(['sourceNutrient', 'suggestedCanonical'])
            ->get();

        return response()->json(['data' => NutrientMappingReviewResource::collection($reviews)], 200);
    }

    #[OA\Patch(
        path: '/api/nutrient-mapping-reviews/{nutrientMappingReview}',
        summary: 'Resolve a nutrient mapping review',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['decision'],
                properties: [
                    new OA\Property(property: 'decision',     type: 'string', enum: ['merge', 'parent', 'keep', 'reject']),
                    new OA\Property(property: 'canonical_id', type: 'integer', nullable: true, description: 'Override the suggested canonical. Required when the review has no suggestion.'),
                ]
            )
        ),
        tags: ['NutrientMappingReviews'],
        parameters: [
            new OA\Parameter(name: 'nutrientMappingReview', in: 'path', required: true, description: 'Review ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Resolved review'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 409, description: 'Already resolved'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function resolve(NutrientMappingReviewRequest $request, NutrientMappingReview $nutrientMappingReview): JsonResponse
    {
        if ($nutrientMappingReview->status !== 'pending') {
            return response()->json(['message' => 'This review has already been resolved.'], 409);
        }

        $decision  = $request->input('decision');
        $canonical = null;

        if (in_array($decision, ['merge', 'parent'])) {
            if ($request->filled('canonical_id')) {
                $canonical = Nutrient::find($request->integer('canonical_id'));
                if (!$canonical) {
                    return response()->json(['message' => 'The specified nutrient was not found.'], 422);
                }
            } elseif (!$nutrientMappingReview->suggested_canonical_id) {
                return response()->json(['message' => 'This review has no suggested canonical. Provide canonical_id.'], 422);
            }
        }

        match ($decision) {
            'merge'  => $nutrientMappingReview->executeMerge($canonical),
            'parent' => $nutrientMappingReview->executeParent($canonical),
            'keep'   => $nutrientMappingReview->executeKeep(),
            'reject' => $nutrientMappingReview->executeReject(),
        };

        $nutrientMappingReview->load(['sourceNutrient', 'suggestedCanonical']);

        return response()->json(new NutrientMappingReviewResource($nutrientMappingReview), 200);
    }
}
