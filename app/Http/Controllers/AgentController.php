<?php

namespace App\Http\Controllers;

use App\AI\AgentOrchestrator;
use App\Exceptions\LlmRequestFailedException;
use App\Exceptions\LlmUnavailableException;
use App\Http\Requests\AgentRequest;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class AgentController extends Controller
{
    #[OA\Post(
        path: '/api/agent',
        summary: 'Ask the AI nutrition agent a question',
        security: [['frontend' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['prompt'],
                properties: [new OA\Property(property: 'prompt', type: 'string', example: 'What are the benefits of vitamin C?')]
            )
        ),
        tags: ['Agent'],
        responses: [
            new OA\Response(response: 200, description: 'AI response', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'response', type: 'string', example: 'Vitamin C is an essential nutrient...'),
                    new OA\Property(property: 'model', type: 'string', example: 'llama3.2'),
                ]
            )),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 502, description: 'AI service returned unexpected response'),
            new OA\Response(response: 503, description: 'AI service unavailable'),
        ]
    )]
    public function ask(AgentRequest $request, AgentOrchestrator $orchestrator): JsonResponse
    {
        try {
            $response = $orchestrator->run($request->validated('prompt'));
        } catch (LlmUnavailableException) {
            return response()->json(
                ['message' => 'The AI service is currently unavailable. Please try again later.'],
                503
            );
        } catch (LlmRequestFailedException) {
            return response()->json(
                ['message' => 'The AI service returned an unexpected response.'],
                502
            );
        }

        return response()->json([
            'response' => $response,
            'model'    => config('ai.ollama.model'),
        ]);
    }
}
