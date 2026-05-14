<?php

namespace Tests\Feature\E2e;

use App\Services\Auth\TestTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\UsesTestMode;

class VerifyFrontendTestModeTest extends TestCase
{
    use RefreshDatabase, UsesTestMode;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->enableTestMode();
    }

    public function test_bypasses_external_auth_with_valid_test_token(): void
    {
        // If the external auth service is called it would return 503 — a 200 proves it was skipped.
        Http::fake([
            config('nutrients.auth.url_backend') . '*' => Http::response(null, 503),
        ]);

        $this->withHeaders($this->testHeaders())
            ->getJson(route('nutrients.index'))
            ->assertOk();
    }

    public function test_rejects_invalid_signature(): void
    {
        $service = app(TestTokenService::class);
        $validToken = $service->generate('sub', 'a@b.com', 'user', 'User');

        // Tamper the signature
        $parts = explode('.', $validToken);
        $parts[2] = 'invalidsignature';
        $tamperedToken = implode('.', $parts);

        $this->withHeaders(['Authorization' => "Bearer {$tamperedToken}"])
            ->getJson(route('nutrients.index'))
            ->assertUnauthorized();
    }

    public function test_rejects_missing_bearer_token(): void
    {
        $this->getJson(route('nutrients.index'))
            ->assertUnauthorized();
    }

    public function test_does_not_bypass_when_test_mode_disabled(): void
    {
        config(['app.test_mode' => false]);

        // With test mode off, the test token is not accepted; normal auth is required.
        // The real auth service is not running in tests, so we get a connection error
        // handled as 503, OR the missing headers trigger a 401 before any HTTP call.
        $this->withHeaders($this->testHeaders())
            ->getJson(route('nutrients.index'))
            ->assertStatus(401);
    }
}
