<?php
namespace App\Classes\Auth;

class AccessToken
{
    public function __construct(
        public readonly string $accessToken,
        public readonly int    $expiresIn,
        public readonly string $tokenType,
        public readonly ?string $refreshToken = null,
        public readonly ?int    $refreshExpiresIn = null,
        public readonly ?string $scope = null,
        public readonly ?string $idToken = null,
        public readonly ?int    $notBeforePolicy = null,
        public readonly ?string $sessionState = null,
        public readonly array   $raw = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: $data['access_token'],
            tokenType: $data['token_type'] ?? 'Bearer',
            expiresIn: (int) ($data['expires_in'] ?? 0),
            refreshToken: $data['refresh_token'] ?? null,
            refreshExpiresIn: isset($data['refresh_expires_in']) ? (int) $data['refresh_expires_in'] : null,
            scope: $data['scope'] ?? null,
            idToken: $data['id_token'] ?? null,
            notBeforePolicy: $data['not_before_policy'] ?? null,
            sessionState: $data['session_state'] ?? null,
            raw: $data,
        );
    }
}