<?php

namespace App\Http\Controllers;

use App\Services\LoginRedirectService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\JsonResponse;

class LoginController extends Controller
{
    // public function __construct(LoginRedirectService $redirectService) {}

    public function login (Request $request) {
        return response()->json([
            'redirect_uri' => 'http://localhost:9020/login'
        ], 200);
    }

    public function validateAccessToken(): JsonResponse {
        $accessToken = request()->bearerToken();
        $refreshToken = request()->header('X-Refresh-Token');
        $applicationName = request()->header('X-Application-Name');
        $applicationUrl = request()->header('X-Client-Url');

        $response = Http::withToken($accessToken)->withHeaders([
            'X-Refresh-Token' => $refreshToken,
            'X-Application-Name' => $applicationName,
            'X-Client-Url' => $applicationUrl
        ])->get('http://host.docker.internal:9025/api/auth/validate-access-token');
        if($response->status() !== 200) {
            return response()->json(false);
        }
        return response()->json($response->body());
    }

    public function logout(Request $request): JsonResponse {
        $accessToken = $request->bearerToken();
        $refreshToken = $request->header('X-Refresh-Token');
        $applicationName = $request->header('X-Application-Name');
        $applicationUrl = $request->header('X-Client-Url');
        if (!$accessToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $response = Http::withToken($accessToken)->withHeaders([
                'X-Refresh-Token' => $refreshToken,
                'X-Application-Name' => $applicationName,
                'X-Client-Url' => $applicationUrl
            ])->post('http://host.docker.internal:9025/api/auth/logout');
    
            if ($response->status() === 200) {
                return response()->json(['message' => 'Logged out successfully'], 200);
            }

            return response()->json('error', 400);
        } catch (Exception $e) {
            return response()->json($e->getMessage() ?? ['error' => 'Server error'], $e->getCode() ?: 400);
        }
    }
}
