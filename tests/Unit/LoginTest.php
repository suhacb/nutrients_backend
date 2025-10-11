<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\LoginRedirectService;

class LoginTest extends TestCase
{
    public function test_LoginRedirectService_generates_correct_redirect_url()
    {
        $service = new LoginRedirectService();

        $clientId = 'test-client';
        $redirectUri = 'http://host.docker.internal:9010';
        $state = 'random123';

        $url = $service->generateRedirectUrl($clientId, $redirectUri, $state);

        $this->assertStringContainsString('http://host.docker.internal:9020/login', $url);
        // $this->assertStringContainsString("client_id={$clientId}", $url);
        $this->assertStringContainsString("redirect_uri=" . urlencode($redirectUri), $url);
        // $this->assertStringContainsString("state={$state}", $url);
    }
}
