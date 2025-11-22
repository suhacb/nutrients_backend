<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use App\Services\Auth\AuthService;
use App\Services\User\UserService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifyFrontend
{
    public function __construct(private AuthService $authService, private UserService $userService) {}
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $accessToken = $request->bearerToken();
        $appName = $request->header('X-Application-Name');
        $appUrl = $request->header('X-Client-Url');
        $refreshToken = $request->header('X-Refresh-Token');

        // If any are missing, return 401
        if (!$accessToken || !$appName || !$appUrl || !$refreshToken) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 401);
        }

        try {
            $response = $this->authService->validate($accessToken, $refreshToken, $appName, $appUrl);

            if (!$response->successful()) {
                return response()->json([
                    'error' => 'Unauthorized'
                ], 401);
            }
        } catch (RequestException $e) {
            logger()->error('Token validation HTTP error', ['exception' => $e]);
            return response()->json(['error' => 'Token validation service unavailable'], 503);
        } catch (Exception $e) {
            return response()->json($e->getMessage() ?? ['error' => 'Server error'], $e->getCode() ?: 400);
        }

        try {
            $user = $this->userService->handleUserFromToken($accessToken);
            if (!$user) {
                return response()->json(['error' => 'User could not be retrieved'], 500);
            }
            Auth::login($user);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'Invalid token'], 400);
        } catch (\Exception $e) {
            logger()->error('Handle user token error', ['exception' => $e]);
            return response()->json(['error' => 'Handle user token error'], 500);
        }

        return $next($request);
    }
}
