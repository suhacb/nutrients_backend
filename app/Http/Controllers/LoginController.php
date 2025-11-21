<?php

namespace App\Http\Controllers;

use App\Services\Auth\AuthService;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\JsonResponse;

class LoginController extends Controller
{
    protected string $accessToken;
    protected string $refreshToken;
    protected string $applicationName;
    protected string $applicationUrl;

    public function __construct(protected AuthService $service) {
        $this->accessToken = request()->bearerToken() ?? null;
        $this->refreshToken = request()->header('X-Refresh-Token') ?? null;
        $this->applicationName = request()->header('X-Application-Name') ?? null;
        $this->applicationUrl = request()->header('X-Client-Url') ?? null;
    }

    public function login (): JsonResponse {
        return response()->json([
            'redirect_uri' => $this->service->login()
        ], 200);
    }

    public function validateAccessToken(): JsonResponse {
        if(!$this->accessToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $response = $this->service->validate($this->accessToken, $this->refreshToken, $this->applicationName, $this->applicationUrl);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json(false);
        } catch (RequestException $e) {
            logger()->error('Token validation HTTP error', ['exception' => $e]);
            return response()->json(['error' => 'Token validation service unavailable'], 503);
        } catch (Exception $e) {
            return response()->json($e->getMessage() ?? ['error' => 'Server error'], $e->getCode() ?: 400);
        }
    }

    public function logout(): JsonResponse {
        if (!$this->accessToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $response = $this->service->logout($this->accessToken, $this->refreshToken, $this->applicationName, $this->applicationUrl);
            if ($response->status() === 200) {
                return response()->json(['message' => 'Logged out successfully'], 200);
            }

            return response()->json('error', 400);
        } catch (Exception $e) {
            return response()->json($e->getMessage() ?? ['error' => 'Server error'], $e->getCode() ?: 400);
        }
    }
}
