<?php
namespace Tests;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

trait LoginTestUser
{
    protected string | null $accessToken;
    protected string | null $refreshToken;
    protected string $appName;
    protected string $appUrl;
    protected string $authUrl;

    private function login(): array
    {
        $this->appName = config('nutrients.name');
        $this->appUrl = config('nutrients.frontend.url') . ':' . config('nutrients.frontend.port');
        $this->authUrl = config('nutrients.auth.url_backend') . ':' . config('nutrients.auth.port_backend');

        $finalUrl = $this->authUrl . '/api/auth/login';

        $response = Http::withHeaders([
            'X-Application-Name' => $this->appName,
            'X-Client-Url' => $this->appUrl,
        ])->post($finalUrl, [
            'username' => config('nutrients.testuser.username'),
            'password' => config('nutrients.testuser.password')
        ]);
        $token = $response->json();

        $this->accessToken = $token['access_token'] ?? null;
        $this->refreshToken = $token['refresh_token'] ?? null;

        /**
         * Immediatelly validate access token to ensure user from token
         */
        $this->withHeaders($this->makeAuthRequestHeader())->getJson(route('auth.validate-access-token'));
        
        return $response->json();
    }

    private function makeAuthRequestHeader(): array
    {
        return [
            'Authorization' => "Bearer {$this->accessToken}",
            'X-Refresh-Token' => $this->refreshToken,
            'X-Application-Name' => $this->appName,
            'X-Client-Url' => $this->appUrl,
        ];
    }

    private function logout(): array
    {
        $finalUrl = $this->authUrl . '/api/auth/logout';

        $response = Http::withHeaders($this->makeAuthRequestHeader())->post($finalUrl, []);
        $this->accessToken = null;
        $this->refreshToken = null;
        return $response->json();
    }
}