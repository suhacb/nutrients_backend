<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Services\Auth\AuthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;

class LoginController extends Controller
{
    protected string | null $accessToken;
    protected string | null $refreshToken;
    protected string | null $applicationName;
    protected string | null $applicationUrl;

    public function __construct(protected AuthService $service) {
        $this->accessToken = request()->bearerToken() ?? null;
        $this->refreshToken = request()->header('X-Refresh-Token') ?? null;
        $this->applicationName = request()->header('X-Application-Name') ?? null;
        $this->applicationUrl = request()->header('X-Client-Url') ?? null;
    }

    #[OA\Post(
        path: '/api/auth/login',
        summary: 'Get OAuth2 redirect URI',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Redirect URI', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'redirect_uri', type: 'string', example: 'https://auth.example.com/oauth/authorize?...')]
            )),
        ]
    )]
    public function login (): JsonResponse {
        return response()->json([
            'redirect_uri' => $this->service->login()
        ], 200);
    }

    #[OA\Get(
        path: '/api/auth/validate-access-token',
        summary: 'Validate a bearer access token',
        security: [['frontend' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Token is valid'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 503, description: 'Auth service unavailable'),
        ]
    )]
    public function validateAccessToken(): JsonResponse {
        if(!$this->accessToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $response = $this->service->validate($this->accessToken, $this->refreshToken, $this->applicationName, $this->applicationUrl);

            if ($response->successful()) {
                $responseData = $response->json();
                if($responseData == true) { return response()->json("true", 200); }
                if($responseData === false) { return response()->json("false", 401); }
                return response()->json($responseData);
            }
            return response()->json("false");
        } catch (RequestException $e) {
            logger()->error('Token validation HTTP error', ['exception' => $e]);
            return response()->json(['error' => 'Token validation service unavailable'], 503);
        } catch (Exception $e) {
            return response()->json($e->getMessage() ?? ['error' => 'Server error'], $e->getCode() ?: 400);
        }
    }

    #[OA\Post(
        path: '/api/auth/logout',
        summary: 'Logout and invalidate tokens',
        security: [['frontend' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Logged out successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 503, description: 'Auth service unavailable'),
        ]
    )]
    public function logout(): JsonResponse {
        if (!$this->accessToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $response = $this->service->logout($this->accessToken, $this->refreshToken, $this->applicationName, $this->applicationUrl);
            if ($response->successful()) {
                Auth::logout();
                return response()->json(['message' => 'Logged out successfully'], 200);
            }

            return response()->json([
                'error' => 'Logout failed',
                'status' => $response->status(),
                'body' => $response->json()
            ], $response->status());

        } catch (RequestException $e) {
            logger()->error('Logout HTTP error', ['exception' => $e]);
            return response()->json(['error' => 'Logout service unavailable'], 503);
        } catch (Exception $e) {
            logger()->error('Logout error', ['exception' => $e]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}
