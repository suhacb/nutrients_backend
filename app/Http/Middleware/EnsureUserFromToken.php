<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\User\UserService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserFromToken
{
    public function __construct(private UserService $service )
    {
        
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $accessToken = $request->bearerToken() ?? null; // Received token, or null
        $tokenToUse = null;
        $response = $next($request);

        if ($response->status() === 200) {
            $data = json_decode($response->getContent(), true);
            if ($data === "true") {
                $tokenToUse = $accessToken;
            } elseif (is_array($data) && isset($data['access_token'])) {
                $tokenToUse = $response->json('access_token');
            }
            if (!$tokenToUse) {
                return $response;
            }
        } else {
            return $response;
        }

        try {
            // Use token to find or create user
            $user = $this->service->handleUserFromToken($tokenToUse);
            Auth::login($user);
        } catch (\Exception $e) {
            // Log but do not block response
            logger()->error('EnsureUserFromToken error', ['exception' => $e]);
        }

        return $response;
    }
}
