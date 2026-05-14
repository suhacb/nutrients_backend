<?php

namespace Tests\Feature\E2e;

use App\Classes\User\TokenParser;
use Tests\TestCase;

class TokenParserTest extends TestCase
{
    private TokenParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new TokenParser();
    }

    public function test_parses_base64url_payload(): void
    {
        $payload = rtrim(strtr(base64_encode(json_encode([
            'sub'                => 'test-user',
            'email'              => 'test@example.com',
            'preferred_username' => 'testuser',
            'name'               => 'Test User',
        ])), '+/', '-_'), '=');

        $token = 'header.' . $payload . '.signature';
        $claims = $this->parser->parse($token);

        $this->assertEquals('test-user', $claims['sub']);
        $this->assertEquals('test@example.com', $claims['email']);
        $this->assertEquals('testuser', $claims['preferred_username']);
    }

    public function test_parses_standard_base64_payload(): void
    {
        $payload = base64_encode(json_encode([
            'sub'   => 'test-user',
            'email' => 'test@example.com',
        ]));

        $token = 'header.' . $payload . '.signature';
        $claims = $this->parser->parse($token);

        $this->assertEquals('test-user', $claims['sub']);
        $this->assertEquals('test@example.com', $claims['email']);
    }
}
