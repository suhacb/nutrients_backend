<?php
namespace Tests;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

trait LoginTestUser
{
    private function login(): array
    {
        $finalUrl = $this->authUrl . '/api/auth/login';

        $response = Http::withHeaders([
            'X-Application-Name' => $this->appName,
            'X-Client-Url' => $this->appUrl,
        ])->post($finalUrl, [
            'username' => config('nutrients.testuser.username'),
            'password' => config('nutrients.testuser.password')
        ]);
        
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
}