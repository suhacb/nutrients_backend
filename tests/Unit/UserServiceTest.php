<?php

namespace Tests\Unit;

use Mockery;
use Tests\TestCase;
use App\Models\User;
use App\Classes\User\TokenParser;
use App\Services\User\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;

class UserServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $parser;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = Mockery::mock(TokenParser::class);
        $this->service = new UserService($this->parser);
    }

    public function test_it_returns_existing_user_when_found(): void
    {
        // Create new user
        $uuid = $this->faker()->uuid();
        $username = $this->faker()->userName();
        $name = $this->faker()->name();
        $fname = $this->faker->firstName();
        $lname = $this->faker()->lastName();
        $email = $this->faker()->email();

        $user = User::factory()->create([
            'external_id' => $uuid,
            'username' => $username,
            'name' => $name,
            'fname' => $fname,
            'lname' => $lname,
            'email' => $email,
        ]);

        $claims = [
            'sub' => $uuid,
            'email' => $email,
            'name' => $name,
            'preferred_username' => $username,
            'given_name' => $fname,
            'family_name' => $lname,
        ];
  
        $this->parser->shouldReceive('parse')->once()->andReturn($claims);

        $result = $this->service->handleUserFromToken('fake-token');
        $this->assertEquals($user->id, $result->id);
        Auth::login($user);

        $this->assertEquals(Auth::user()->id, $result->id);
    }

    public function test_it_creates_new_user_if_not_exists(): void
    {
        // Create claims of non-existing user
        // Create new user
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

        $this->parser->shouldReceive('parse')->once()->andReturn($claims);

        $result = $this->service->handleUserFromToken('fake-token');

        $this->assertDatabaseHas('users', [
            'external_id' => $uuid,
            'email' => $email,
            'username' => $username,
            'name' => $name
        ]);
    }
    
    public function test_it_throws_if_token_is_missing_required_claims(): void
    {
        $this->parser->shouldReceive('parse')->once()->andReturn(['email' => 'x@example.com']);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->handleUserFromToken('fake-token');
    }
    
}
