<?php

namespace Tests;

use App\Services\Auth\TestTokenService;

trait UsesTestMode
{
    protected function enableTestMode(): void
    {
        config(['app.test_mode' => true]);
    }

    protected function testToken(string $role = 'admin'): string
    {
        return app(TestTokenService::class)->generate(
            "test-sub-{$role}",
            "test-{$role}@e2e.local",
            "test_{$role}",
            'Test ' . ucfirst($role),
            $role
        );
    }

    protected function testHeaders(string $role = 'admin'): array
    {
        return ['Authorization' => 'Bearer ' . $this->testToken($role)];
    }

    protected function testModeHeaders(string $role = 'admin'): array
    {
        return array_merge($this->testHeaders($role), ['X-Test-Mode' => 'true']);
    }
}
