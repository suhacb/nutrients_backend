<?php

namespace App\Services\Auth;

class TestTokenService
{
    private string $secret;

    public function __construct()
    {
        $appKey = config('app.key');
        $this->secret = str_starts_with($appKey, 'base64:')
            ? base64_decode(substr($appKey, 7))
            : $appKey;
    }

    public function generate(
        string $sub,
        string $email,
        string $username,
        string $name,
        string $role = 'admin'
    ): string {
        $now = time();

        $header  = $this->base64urlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64urlEncode(json_encode([
            'sub'                => $sub,
            'email'              => $email,
            'preferred_username' => $username,
            'name'               => $name,
            'given_name'         => '',
            'family_name'        => '',
            'roles'              => [$role],
            'iat'                => $now,
            'exp'                => $now + 86400,
        ]));

        $signature = $this->base64urlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", $this->secret, true)
        );

        return "{$header}.{$payload}.{$signature}";
    }

    public function generateIdToken(string $sub, string $email): string
    {
        $now = time();

        $header  = $this->base64urlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64urlEncode(json_encode([
            'iss'   => config('app.url'),
            'sub'   => $sub,
            'email' => $email,
            'iat'   => $now,
            'exp'   => $now + 86400,
        ]));

        $signature = $this->base64urlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", $this->secret, true)
        );

        return "{$header}.{$payload}.{$signature}";
    }

    public function verify(string $token): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$header, $payload, $signature] = $parts;
        $expected = $this->base64urlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", $this->secret, true)
        );

        return hash_equals($expected, $signature);
    }

    public function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return [];
        }

        $decoded = json_decode($this->base64urlDecode($parts[1]), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64urlDecode(string $data): string
    {
        $padded = $data . str_repeat('=', (4 - strlen($data) % 4) % 4);
        return base64_decode(strtr($padded, '-_', '+/'));
    }
}
