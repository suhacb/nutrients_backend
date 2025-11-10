<?php

namespace App\Services\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class TokenValidationService {
    protected string $authBackendUrl;

    public function __construct()
    {
        $this->authBackendUrl = config(
            'services.auth.url',
            'http://host.docker.internal:9020/api'
        );
    }
    public function validate(string $accessToken, ?string $refreshToken = null): array | bool | null
    {
        $response = Http::withToken($accessToken)
            ->withHeaders([
                'X-Application-Name' => config('app.name') ?? 'nutrients_backend',
                'X-Client-Url' => config('app.url') ?? 'http://localhost:9015'
            ])
            ->get($this->authBackendUrl . '/auth/validate-access-token');
        return $response->json();
    }
}