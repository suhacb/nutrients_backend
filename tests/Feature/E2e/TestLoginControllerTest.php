<?php

namespace Tests\Feature\E2e;

use App\Services\Auth\TestTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\UsesTestMode;

class TestLoginControllerTest extends TestCase
{
    use RefreshDatabase, UsesTestMode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableTestMode();
    }

    public function test_returns_404_when_test_mode_disabled(): void
    {
        config(['app.test_mode' => false]);

        $this->postJson('/api/auth/test-login')->assertNotFound();
    }

    public function test_returns_keycloak_shaped_response(): void
    {
        $response = $this->postJson('/api/auth/test-login')->assertOk();

        $response->assertJsonStructure([
            'access_token',
            'refresh_token',
            'id_token',
            'token_type',
            'expires_in',
            'session_state',
            'scope',
            'not-before-policy',
            'refresh_expires_in',
        ]);
    }

    public function test_access_token_has_required_claims(): void
    {
        $json = $this->postJson('/api/auth/test-login')->assertOk()->json();

        $service = app(TestTokenService::class);
        $claims = $service->decode($json['access_token']);

        $this->assertArrayHasKey('sub', $claims);
        $this->assertArrayHasKey('email', $claims);
        $this->assertArrayHasKey('preferred_username', $claims);
        $this->assertArrayHasKey('name', $claims);
        $this->assertArrayHasKey('given_name', $claims);
        $this->assertArrayHasKey('family_name', $claims);
        $this->assertArrayHasKey('roles', $claims);
    }

    public function test_role_is_included_in_claims(): void
    {
        $json = $this->postJson('/api/auth/test-login', ['role' => 'viewer'])->assertOk()->json();

        $service = app(TestTokenService::class);
        $claims = $service->decode($json['access_token']);

        $this->assertContains('viewer', $claims['roles']);
    }

    public function test_default_role_is_admin(): void
    {
        $json = $this->postJson('/api/auth/test-login')->assertOk()->json();

        $service = app(TestTokenService::class);
        $claims = $service->decode($json['access_token']);

        $this->assertContains('admin', $claims['roles']);
    }

    public function test_expires_in_is_86400(): void
    {
        $json = $this->postJson('/api/auth/test-login')->assertOk()->json();

        $this->assertEquals(86400, $json['expires_in']);
    }

    public function test_token_signature_is_verifiable(): void
    {
        $json = $this->postJson('/api/auth/test-login')->assertOk()->json();

        $service = app(TestTokenService::class);
        $this->assertTrue($service->verify($json['access_token']));
    }
}
