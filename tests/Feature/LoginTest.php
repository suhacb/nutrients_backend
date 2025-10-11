<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_LoginController_login_returns_redirect_url_for_valid_request()
    {
        $payload = [
            'client_id' => 'test-client',
            'redirect_uri' => 'https://client.example.com/callback',
            'state' => 'random123'
        ];

        $response = $this->postJson(route('auth.login'), $payload);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'url'
                 ])
                 ->assertJson(['status' => 'redirect'])
                 ->assertJson(fn ($json) =>
                     str_contains($json['url'], 'https://auth.example.com/login')
                 );
    }

    /** @test */
    public function it_fails_validation_for_missing_client_id()
    {
        $payload = [
            'redirect_uri' => 'https://client.example.com/callback'
        ];

        $response = $this->postJson('/api/login-redirect', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['client_id']);
    }
}