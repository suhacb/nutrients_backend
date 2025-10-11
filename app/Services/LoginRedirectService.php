<?php

namespace App\Services;

use InvalidArgumentException;

class LoginRedirectService {
    protected string $oauthBaseUrl;
    protected array $clients;

    public function __construct()
    {
        // $this->oauthBaseUrl = config('oauth.base_url', 'https://auth.example.com');
        // $this->clients = config('oauth.clients', []);
    }

    /**
     * Generate the OAuth login redirect URL
     *
     * @param string $clientId
     * @param string $redirectUri
     * @param string|null $state
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function generateRedirectUrl(string $clientId, string $redirectUri, ?string $state = null): string
    {
        return 'http://host.docker.internal:9020/login?' . http_build_query([
            'redirect_uri' => 'http://host.docker.internal:9010']
        );

        // Check client is allowed
        if (!array_key_exists($clientId, $this->clients)) {
            throw new InvalidArgumentException("Unknown client_id: {$clientId}");
        }

        // Check redirect URI is allowed for this client
        $allowedRedirects = $this->clients[$clientId]['redirect_uris'] ?? [];
        if (!in_array($redirectUri, $allowedRedirects)) {
            throw new InvalidArgumentException("Redirect URI not allowed for client_id: {$clientId}");
        }

        // Build query parameters
        $query = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
        ];

        if ($state) {
            $query['state'] = $state;
        }

        return $this->oauthBaseUrl . '/login?' . http_build_query($query);
    }
}