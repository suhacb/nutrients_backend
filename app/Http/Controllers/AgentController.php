<?php

namespace App\Http\Controllers;

use App\AI\Contracts\LlmClientContract;
use App\Exceptions\LlmRequestFailedException;
use App\Exceptions\LlmUnavailableException;
use App\Http\Requests\AgentRequest;
use Illuminate\Http\JsonResponse;

class AgentController extends Controller
{
    public function ask(AgentRequest $request, LlmClientContract $llm): JsonResponse
    {
        $messages = [
            ['role' => 'system', 'content' => config('ai.agent.system_prompt')],
            ['role' => 'user',   'content' => $request->validated('prompt')],
        ];

        try {
            $response = $llm->chat($messages);
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
