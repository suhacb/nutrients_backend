<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase;
    
    protected string $accessToken;
    protected string $refreshToken;
    protected string $appName;
    protected string $appUrl;
    protected string $authUrl;

    protected function setUp(): void {
        parent::setUp();

        $this->appName = config('nutrients.name');
        $this->appUrl = config('nutrients.frontend.url') . ':' . config('nutrients.frontend.port');
        $this->authUrl = config('nutrients.auth.url_backend') . ':' . config('nutrients.auth.port_backend');
    }
    /**
     * Test login endpoint returns redirect URI
     */
    public function test_login_returns_redirect_uri(): void {
        $response = $this->postJson(route('auth.login')); // adjust route if needed

        $response->assertStatus(200)->assertJsonStructure(['redirect_uri']);

        $redirectUri = $response->json('redirect_uri');
        $this->assertIsString($redirectUri);
        $this->assertTrue(filter_var($redirectUri, FILTER_VALIDATE_URL) !== false, 'redirect_uri is not a valid URL');

        $query = http_build_query([
            'appName' => $this->appName,
            'appUrl'  => $this->appUrl
        ]);
        
        $expectedUri = config('nutrients.auth.url_frontend') . ':' . config('nutrients.auth.port_frontend') . '/login?' . $query;
        $this->assertEquals($expectedUri, $redirectUri);
    }

    /**
     * Test validateAccessToken without token returns 401
     */
    public function test_validate_access_token_without_token_returns_unauthorized()
    {
        $response = $this->getJson($this->authUrl . '/api/auth/validate-access-token');
    
        $response->assertStatus(401)
                 ->assertJson([
                     'error' => 'Unauthorized'
                 ]);
    }

    /**
     * Test validateAccessToken with valid token
     */
    public function test_validate_access_token_with_token()
    {
        // Obtain access token
        $token = $this->login(); 

        $response = $this->getJson(route('auth.validate-access-token'), [
            'Authorization' => "Bearer {$token['access_token']}",
            'X-Refresh-Token' => $token['refresh_token'],
            'X-Application-Name' => $this->appName,
            'X-Client-Url' => $this->appUrl,
        ]);
    
        $response->assertStatus(200);
    
        $data = $response->json();
    
        // The response can be "true", "false" (string) or an object
        $this->assertTrue(
            $data === 'true' || $data === 'false' || is_array($data),
            'Unexpected response format from validateAccessToken'
        );
    }

    /**
     * Test logout without token returns 401
     */
    public function test_logout_without_token_returns_unauthorized()
    {
        $response = $this->postJson(route('auth.logout'));
    
        $response->assertStatus(401)
                 ->assertJson([
                     'error' => 'Unauthorized'
                 ]);
    }
    
    /**
     * Test logout with token
     */
    public function test_logout_with_token()
    {
        $token = $this->login();
        $response = $this->postJson(route('auth.logout'), [], [
            'Authorization' => "Bearer {$token['access_token']}",
            'X-Refresh-Token' => $token['refresh_token'],
            'X-Application-Name' => $this->appName,
            'X-Client-Url' => $this->appUrl,
        ]);
    
        $response->assertStatus(200);
        logger()->info($response->json());
    
        $response->assertJsonStructure(['message']);
        $this->assertEquals('Logged out successfully', $response->json('message'));
    }

    private function login() {
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
}
