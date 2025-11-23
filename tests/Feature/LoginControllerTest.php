<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Classes\User\TokenParser;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Tests\LoginTestUser;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase, LoginTestUser;

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
        $token = $this->login();
        $this->accessToken = $token['access_token'] ?? null;
        $this->refreshToken = $token['refresh_token'] ?? null;
    }
    /**
     * Test login endpoint returns redirect URI
     */
    public function test_login_returns_redirect_uri(): void
    {
        $response = $this->postJson(route('auth.login'));

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
    public function test_validate_access_token_with_token(): void
    {
        $response = $this->withHeaders($this->makeAuthRequestHeader())->getJson(route('auth.validate-access-token'));
    
        $response->assertStatus(200);
    
        $data = $response->json();
    
        // The response can be "true", "false" (string) or an object
        $this->assertTrue(
            $data === 'true' || $data === 'false' || is_array($data),
            'Unexpected response format from validateAccessToken'
        );

        $parser = new TokenParser();
        $parsedToken = $parser->parse($this->accessToken);

        $this->assertDatabaseHas('users', [
            'external_id' => $parsedToken['sub']
        ]);
    }

    /**
     * Test logout without token returns 401
     */
    public function test_logout_without_token_returns_unauthorized(): void
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
    public function test_logout_with_token(): void
    {
        $token = $this->login();
        $response = $this->postJson(route('auth.logout'), [], [
            'Authorization' => "Bearer {$token['access_token']}",
            'X-Refresh-Token' => $token['refresh_token'],
            'X-Application-Name' => $this->appName,
            'X-Client-Url' => $this->appUrl,
        ]);
    
        $response->assertStatus(200);
    
        $response->assertJsonStructure(['message']);
        $this->assertEquals('Logged out successfully', $response->json('message'));
        $this->assertNull(Auth::user());
    }

    /**
     * Test protected route with valid user
     */
    public function test_it_calls_protected_route_and_logs_in_user_from_keycloak_token(): void
    {
        // Obtain real access token from Keycloak
        $token = $this->login();

        $accessToken = $token['access_token'];
        $refreshToken = $token['refresh_token'];

        $this->assertNotEmpty($accessToken, 'Access token should be present');

        // Call protected route with required headers
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'X-Application-Name' => $this->appName,
            'X-Client-Url' => $this->appUrl,
            'X-Refresh-Token' => $refreshToken,
        ])->get(route('test'));

        $response->assertStatus(200);

        // Assert user is created and logged in
        $parser = new TokenParser();
        $claims = $parser->parse($accessToken);

        $this->assertDatabaseHas('users', [
            'external_id' => $claims['sub'],
            'email' => $claims['email'],
            'username' => $claims['preferred_username'],
            'name' => $claims['name'],
        ]);

        $user = Auth::user();
        $this->assertNotNull($user, 'User should be logged in');
        $this->assertEquals($claims['sub'], $user->external_id);
        $this->assertEquals($claims['email'], $user->email);
        $this->assertEquals($claims['preferred_username'], $user->username);
        $this->assertEquals($claims['name'], $user->name);
    }

    /**
     * Test protected route with invalid user
     */
    public function test_it_calls_protected_route_with_invalid_user(): void
    {
        $invalidAccessToken = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6IjdYc2pHZ2xlRmpKTDZTbHYwNlFYSUIta1NnWnUxUUwwbEdKcTA2M2lMSlkifQ.eyJleHAiOjE3NjM3OTI5OTIsImlhdCI6MTc2Mzc5MjY5MiwianRpIjoib25ydHJvOmE3YzZhNWU2LTQ2YTEtMGZlYS04NjFmLTI0ZmE1NDQ5OGIzNiIsImlzcyI6Imh0dHA6Ly9ob3N0LmRvY2tlci5pbnRlcm5hbDo3MDgwL3JlYWxtcy9udXRyaWVudHMiLCJhdWQiOiJhY2NvdW50Iiwic3ViIjoiYjQ5ZjE4NzUtZTI0Zi00ZTE3LWJhODYtZmFiZWU4YWZlMDc3IiwidHlwIjoiQmVhcmVyIiwiYXpwIjoibnV0cmllbnRzLWNsaWVudCIsInNpZCI6ImY0NjAxMDcwLTVjNjQtMjI4YS1mMjU2LWQ0MzczZTY5MGY3NyIsImFjciI6IjEiLCJhbGxvd2VkLW9yaWdpbnMiOlsiaHR0cDovL2xvY2FsaG9zdCJdLCJyZWFsbV9hY2Nlc3MiOnsicm9sZXMiOlsiZGVmYXVsdC1yb2xlcy1udXRyaWVudHMiLCJvZmZsaW5lX2FjY2VzcyIsInVtYV9hdXRob3JpemF0aW9uIl19LCJyZXNvdXJjZV9hY2Nlc3MiOnsiYWNjb3VudCI6eyJyb2xlcyI6WyJtYW5hZ2UtYWNjb3VudCIsIm1hbmFnZS1hY2NvdW50LWxpbmtzIiwidmlldy1wcm9maWxlIl19fSwic2NvcGUiOiJvcGVuaWQgZW1haWwgcHJvZmlsZSIsImVtYWlsX3ZlcmlmaWVkIjp0cnVlLCJuYW1lIjoiVGVzdCBVc2VyIiwicHJlZmVycmVkX3VzZXJuYW1lIjoidGVzdCIsImdpdmVuX25hbWUiOiJUZXN0IiwiZmFtaWx5X25hbWUiOiJVc2VyIiwiZW1haWwiOiJ0ZXN0QHN1aGFjLmV1In0.YJpbpATBHHHkevpdxnuvw4ePT07uSER_OC4F4M-Sn7vlVF6KhvuIEE7J2X_TiHQ0vF-NO_nowN7UiQAMvxTsnyb4Ia27d2bit5ZzefxdazFwllOKHKhWJGq8704s0Drq0PZ6sgbailTqE3J8sbtVusPEoO0vUy6JWT_u3Rtghrn4e2oAiDEEU6hYhRqeCBpMOOmjwrob1gFPOzTUH6-crbCHVJf6rwMeZ15rwwMi_YPKEB3JBTcWQjUUFZ-gMc2VTIHBIduesVj4aLEVQsu3yIpngnmE8O5loBAQm02Fa0SugttLlaA25EusdHoE9NbNURhX3-ok3cVZ9-N3MlzP8Q';
        // Call protected route with required headers but invalid tokens
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $invalidAccessToken,
            'X-Application-Name' => $this->appName,
            'X-Client-Url' => $this->appUrl,
            'X-Refresh-Token' => 'invalid-refresh-token',
        ])->post(route('test'));

        $this->assertTrue($response->status() >= 300);
        $this->assertNull(Auth::user());
    }
}
