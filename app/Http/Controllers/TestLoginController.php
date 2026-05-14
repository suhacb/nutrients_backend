<?php

namespace App\Http\Controllers;

use App\Services\Auth\TestTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TestLoginController extends Controller
{
    public function __construct(private TestTokenService $tokenService) {}

    #[OA\Post(
        path: '/api/auth/test-login',
        summary: 'Generate a test JWT (E2E only — unavailable when APP_TEST_MODE is false)',
        tags: ['E2E'],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'role', type: 'string', example: 'admin', description: 'Role to embed in the token claims (defaults to "admin")'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Keycloak-shaped token response',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'access_token', type: 'string'),
                        new OA\Property(property: 'refresh_token', type: 'string'),
                        new OA\Property(property: 'id_token', type: 'string'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                        new OA\Property(property: 'expires_in', type: 'integer', example: 86400),
                        new OA\Property(property: 'session_state', type: 'string'),
                        new OA\Property(property: 'scope', type: 'string'),
                        new OA\Property(property: 'not-before-policy', type: 'integer'),
                        new OA\Property(property: 'refresh_expires_in', type: 'integer'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Not available — APP_TEST_MODE is false'),
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        $role     = $request->input('role', 'admin');
        $sub      = "test-sub-{$role}";
        $email    = "test-{$role}@e2e.local";
        $username = "test_{$role}";
        $name     = 'Test ' . ucfirst($role);

        $accessToken = $this->tokenService->generate($sub, $email, $username, $name, $role);
        $idToken     = $this->tokenService->generateIdToken($sub, $email);

        return response()->json([
            'access_token'       => $accessToken,
            'refresh_token'      => 'test-refresh-token',
            'id_token'           => $idToken,
            'token_type'         => 'Bearer',
            'expires_in'         => 86400,
            'session_state'      => 'test-session-state',
            'scope'              => 'openid email profile',
            'not-before-policy'  => 0,
            'refresh_expires_in' => 0,
        ]);
    }
}
