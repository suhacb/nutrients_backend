<?php

namespace App\Http\Controllers;

use App\Services\Auth\TestTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestLoginController extends Controller
{
    public function __construct(private TestTokenService $tokenService) {}

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
