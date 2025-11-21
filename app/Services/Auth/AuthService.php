<?php
namespace App\Services\Auth;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AuthService {
    protected string $appName;
    protected string $authUrlFrontend;
    protected int $authPortFrontend;
    protected string $authUrlBackend;
    protected int $authPortBackend;
    protected string $appBackendUrl;
    protected int $appBackendPort;
    protected string $appFrontendUrl;
    protected int $appFrontendPort;

    public function __construct()
    {
        $this->appName = config('nutrients.name');
        $this->authUrlFrontend = config('nutrients.auth.url_frontend');
        $this->authPortFrontend = config('nutrients.auth.port_frontend');
        $this->authUrlBackend = config('nutrients.auth.url_backend');
        $this->authPortBackend = config('nutrients.auth.port_backend');
        $this->appBackendUrl = config('nutrients.backend.url');
        $this->appBackendPort = config('nutrients.backend.port');
        $this->appFrontendUrl = config('nutrients.frontend.url');
        $this->appFrontendPort = config('nutrients.frontend.port');
    }

    public function login(): string {
        $query = http_build_query([
            'appName' => $this->appName,
            'appUrl'  => $this->appFrontendUrl . ':' . $this->appFrontendPort
        ]);

        $finalUrl = "{$this->authUrlFrontend}:{$this->authPortFrontend}/login?{$query}";
        return $finalUrl;
    }

    public function validate(string $accessToken, string $refreshToken, string $applicationName, string $applicationUrl): Response {
        $finalUrl = "{$this->authUrlBackend}:{$this->authPortBackend}/api/auth/validate-access-token";

        return Http::withToken($accessToken)->withHeaders([
            'X-Refresh-Token' => $refreshToken,
            'X-Application-Name' => $applicationName,
            'X-Client-Url' => $applicationUrl
        ])->throw()->get($finalUrl);
    }

    public function logout(string $accessToken, string $refreshToken, string $applicationName, string $applicationUrl): Response {
        $finalUrl = "{$this->authUrlBackend}:{$this->authPortBackend}/api/auth/logout";
        
        return Http::withToken($accessToken)->withHeaders([
            'X-Refresh-Token' => $refreshToken,
            'X-Application-Name' => $applicationName,
            'X-Client-Url' => $applicationUrl
        ])->throw()->post($finalUrl);
    }
}