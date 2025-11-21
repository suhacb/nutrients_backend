<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_LoginController_login_returns_redirect_url_for_valid_request()
    {
        $response = $this->postJson(route('auth.login'));
        $response->assertStatus(200)->assertJsonStructure(['redirect_uri']);
        
        $redirectUri = $response->json('redirect_uri');
        $parsed = parse_url($redirectUri);
        parse_str($parsed['query'], $queryParams);

        $this->assertEquals(config('nutrients.auth.url_frontend'), $parsed['scheme'] . '://' . $parsed['host']);
        $this->assertEquals(config('nutrients.auth.port_frontend'), $parsed['port']);
        $this->assertEquals(config('nutrients.name'), $queryParams['appName']);
        $this->assertEquals(config('nutrients.frontend.url') . ':' . config('nutrients.frontend.port'), $queryParams['appUrl']);
    }
}