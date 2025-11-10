<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Services\Auth\TokenValidationService;

class TokenValidationServiceTest extends TestCase
{
    protected TokenValidationService $service;
    protected $authBackendUrl = '*host.docker.internal:9020/api';

    protected function setUp(): void {
        parent::setUp();
        $this->service = app(TokenValidationService::class);
    }

    public function test_it_returns_true_when_auth_backend_confirms_valid_token()
    {
        Http::fake([$this->authBackendUrl . '/auth/validate-access-token'
            => Http::response(json_encode('true'), 200)
        ]);

        $response = $this->service->validate('valid_token');
        $this->assertTrue($response);
        $this->assertIsNotArray($response);
    }

    public function test_it_returns_new_token_when_auth_backend_refreshes()
    {
        $refreshedToken = [
                'access_token' => 'valid_access_token',
                'token_type' => 'Bearer',
                'expires_in' => 300,
                'refresh_token' => 'valid_refresh_token',
                'refresh_expires_in' => 1800,
                'scope' => 'openid profile email',
                'id_token' => 'xyz',
                'not_before_policy' => '1762719225',
                'session_state' => '47f1f2ee-b2de-425b-4e58-17e93de01215'
        ];

        Http::fake([
            $this->authBackendUrl . '/auth/validate-access-token' => Http::response(json_encode($refreshedToken), 200),
        ]);

        $response = $this->service->validate('expired_and_refreshable_token');

        $this->assertEquals($refreshedToken, $response);
    }

    public function test_it_returns_false_when_auth_backend_declares_invalid_token()
    {
        Http::fake([
            $this->authBackendUrl . '/auth/validate-access-token' => Http::response(json_encode(false), 200),
        ]);

        $response = $this->service->validate('invalid_token');

        $this->assertFalse($response);
    }

    public function test_it_handles_unreachable_auth_backend_gracefully()
    {
        Http::fake([
            $this->authBackendUrl . '/auth/validate-access-token' => Http::response(json_encode(['error' => 'Unauthorized']), 401),
        ]);

        $response = $this->service->validate('any_token');

        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('unauthorized', strtolower($response['error']));
    }
}
