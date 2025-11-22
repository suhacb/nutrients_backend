<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\Auth\AuthService;
use App\Services\User\UserService;
use Illuminate\Support\Facades\Auth;
use App\Http\Middleware\VerifyFrontend;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VerifyFrontendMiddlewareTest extends TestCase
{
    use RefreshDatabase, WithFaker;
    protected $authService;
    protected $userService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authService = Mockery::mock(AuthService::class);
        $this->userService = Mockery::mock(UserService::class);
    }
    
    public function test_it_returns_401_if_required_headers_or_token_missing()
    {
        $request = Request::create(route('auth.logout'), 'POST');

        $middleware = new VerifyFrontend($this->authService, $this->userService);

        $response = $middleware->handle($request, fn($req) => response('Next'));

        $this->assertEquals(401, $response->status());
        $this->assertStringContainsString('Unauthorized', $response->getContent());
    }

    public function token_it_returns_401_if_token_validation_fails()
    {
        $request = Request::create(route('auth.logout'), 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer fake-token',
            'HTTP_X_APPLICATION_NAME' => 'my-app',
            'HTTP_X_CLIENT_URL' => 'http://localhost',
            'HTTP_X_REFRESH_TOKEN' => 'refresh-token'
        ]);

        $this->authService->shouldReceive('validate')
            ->once()
            ->andReturn((object)['successful' => fn() => false]);

        $middleware = new VerifyFrontend($this->authService, $this->userService);

        $response = $middleware->handle($request, fn($req) => response('Next'));

        $this->assertEquals(401, $response->status());
        $this->assertStringContainsString('Unauthorized', $response->getContent());
    }

    public function it_calls_user_service_and_logs_in_user_if_token_valid()
    {
        $uuid = $this->faker()->uuid();
        $username = $this->faker()->userName();
        $name = $this->faker()->name();
        $fname = $this->faker->firstName();
        $lname = $this->faker()->lastName();
        $email = $this->faker()->email();

        $claims = [
            'sub' => $uuid,
            'email' => $email,
            'name' => $name,
            'preferred_username' => $username,
            'given_name' => $fname,
            'family_name' => $lname,
        ];

        $request = Request::create(route('auth.logout'), 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer valid-token',
            'HTTP_X_APPLICATION_NAME' => 'my-app',
            'HTTP_X_CLIENT_URL' => 'http://localhost',
            'HTTP_X_REFRESH_TOKEN' => 'refresh-token'
        ]);

        // AuthService returns successful response
        $this->authService->shouldReceive('validate')
            ->once()
            ->andReturn((object)['successful' => fn() => true]);

        // UserService returns a user and logs them in
        $user = User::factory()->create([
            'external_id' => $uuid,
            'username' => $username,
            'name' => $name,
            'fname' => $fname,
            'lname' => $lname,
            'email' => $email,
        ]);

        $this->userService->shouldReceive('handleUserFromToken')
            ->once()
            ->with('valid-token')
            ->andReturn($user);

        $middleware = new VerifyFrontend($this->authService, $this->userService);

        $response = $middleware->handle($request, fn($req) => response('Next'));

        $this->assertEquals(200, $response->status());
        $this->assertEquals($user->id, Auth::user()->id);
    }
}
