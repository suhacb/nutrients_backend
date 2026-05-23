<?php
namespace App\Classes\User;

use InvalidArgumentException;

class TokenParser {
    public function parse(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Malformed token.');
        }

        [$header, $payload, $signature] = $parts;
        $padded  = $payload . str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $decoded = json_decode(base64_decode(strtr($padded, '-_', '+/')), true);

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Invalid token payload.');
        }

        return $decoded;
    }
}