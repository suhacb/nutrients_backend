<?php
namespace App\Services\User;

use App\Classes\User\TokenParser;
use App\Models\User;

class UserService {
    public function __construct(protected TokenParser $parser) {}

    public function handleUserFromToken(string $token): User
    {
        $claims = $this->parser->parse($token);
        
        if (empty($claims['sub']) || empty($claims['email'] || empty($claims['preferred_username']) || empty($claims['name']))) {
            throw new \InvalidArgumentException('Invalid token claims');
        }

        $user = User::firstOrCreate(
            ['external_id' => $claims['sub']],
            [
                'external_id' => $claims['sub'],
                'username' => $claims['preferred_username'],
                'name' => $claims['name'],
                'fname' => $claims['given_name'] ?? null,
                'lname' => $claims['family_name'] ?? null,
                'email' => $claims['email'],
            ]
        );

        return $user;
    }
}