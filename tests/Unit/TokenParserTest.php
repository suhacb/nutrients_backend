<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Classes\User\TokenParser;

class TokenParserTest extends TestCase
{
    public function test_it_parses_a_valid_jwt_and_returns_claims(): void
    {
        // Sample JWT payload encoded as base64 (header.payload.signature)
        // We'll just encode a JSON payload for simplicity
        $claims = [
            'sub' => 'b49f1875-e24f-4e17-ba86-fabee8afe077',
            'email' => 'test@suhac.eu',
            'name' => 'Test User',
            'preferred_username' => 'test',
            'given_name' => 'Test',
            'family_name' => 'User',
        ];

        // Encode payload to mimic JWT (header.payload.signature)
        $payload = base64_encode(json_encode($claims));
        $token = 'header.' . $payload . '.signature';

        $parser = new TokenParser();
        $result = $parser->parse($token);

        $this->assertEquals($claims, $result);
    }

    public function test_it_throws_exception_for_malformed_token(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $parser = new TokenParser();
        $parser->parse('not-a-valid-token');
    }
}
