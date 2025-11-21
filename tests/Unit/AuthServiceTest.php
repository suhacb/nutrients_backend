<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Auth\AuthService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class AuthServiceTest extends TestCase
{
    protected AuthService $service;

    protected function setUp():void {
        parent::setUp();
        // Set fake config values
        Config::set('nutrients.name', 'nutrients');
        Config::set('nutrients.auth.url_frontend', 'http://localhost');
        Config::set('nutrients.auth.port_frontend', 9020);
        Config::set('nutrients.auth.url_backend', 'http://host.docker.internal');
        Config::set('nutrients.auth.port_backend', 9025);
        Config::set('nutrients.frontend.url', 'http://localhost');
        Config::set('nutrients.frontend.port', 9010);
        Config::set('nutrients.backend.url', 'http://host.docker.internal');
        Config::set('nutrients.backend.port', 9015);

        $this->service = new AuthService();
    }

    public function test_login_returns_correct_url()
    {
        $url = $this->service->login();

        $expectedQuery = http_build_query([
            'appName' => config('nutrients.name'),
            'appUrl'  => config('nutrients.frontend.url') . ':' . config('nutrients.frontend.port'),
        ]);

        $expectedUrl = config('nutrients.auth.url_frontend') . ':' . config('nutrients.auth.port_frontend') . '/login?' . $expectedQuery;
        $this->assertEquals($expectedUrl, $url);
    }

    public function test_validate_sends_correct_http_request()
    {
        Http::fake();

        $accessToken = 'access-token';
        $refreshToken = 'refresh-token';
        $appName = config('nutrients.name');
        $appUrl = config('nutrients.frontend.url') . ':' . config('nutrients.frontend.port');

        $this->service->validate($accessToken, $refreshToken, $appName, $appUrl);

        Http::assertSent(function (Request $request) use ($accessToken, $refreshToken, $appName, $appUrl) {
            return $request->url() === config('nutrients.auth.url_backend') . ':' . config('nutrients.auth.port_backend') . '/api/auth/validate-access-token'
                && $request->hasHeader('Authorization', "Bearer $accessToken")
                && $request->hasHeader('X-Refresh-Token', $refreshToken)
                && $request->hasHeader('X-Application-Name', $appName)
                && $request->hasHeader('X-Client-Url', $appUrl)
                && $request->method() === 'GET';
        });
    }

    public function test_logout_sends_correct_http_request()
    {
        Http::fake();

        $accessToken = 'access-token';
        $refreshToken = 'refresh-token';
        $appName = config('nutrients.name');
        $appUrl = config('nutrients.frontend.url') . ':' . config('nutrients.frontend.port');

        $this->service->logout($accessToken, $refreshToken, $appName, $appUrl);

        Http::assertSent(function (Request $request) use ($accessToken, $refreshToken, $appName, $appUrl) {
            return $request->url() ===  config('nutrients.auth.url_backend') . ':' . config('nutrients.auth.port_backend') . '/api/auth/logout'
                && $request->hasHeader('Authorization', "Bearer $accessToken")
                && $request->hasHeader('X-Refresh-Token', $refreshToken)
                && $request->hasHeader('X-Application-Name', $appName)
                && $request->hasHeader('X-Client-Url', $appUrl)
                && $request->method() === 'POST';
        });
    }

    public function test_validate_returns_expected_response()
    {
        // Fake backend response
        Http::fake([
            config('nutrients.auth.url_backend') . ':' . config('nutrients.auth.port_backend') . '/api/auth/validate-access-token' => Http::response(true, 200),
        ]);

        $accessToken = 'access-token';
        $refreshToken = 'refresh-token';
        $appName = config('nutrients.name');
        $appUrl = config('nutrients.frontend.url') . ':' . config('nutrients.frontend.port');

        $response = $this->service->validate($accessToken, $refreshToken, $appName, $appUrl);
    
        $this->assertEquals(true, $response->json());
    }

    public function test_logout_returns_expected_response()
    {
        $targetUrl = config('nutrients.auth.url_backend') . ':' . config('nutrients.auth.port_backend') . '/api/auth/logout';

        Http::fake([
            $targetUrl => Http::response(['message' => 'Logged out successfully'], 200),
        ]);
    
        $accessToken = 'access-token';
        $refreshToken = 'refresh-token';
        $appName = config('nutrients.name');
        $appUrl = config('nutrients.frontend.url') . ':' . config('nutrients.frontend.port');

        $response = $this->service->logout($accessToken, $refreshToken, $appName, $appUrl);
    
        $this->assertEquals(['message' => 'Logged out successfully'], $response->json());
    }
}
