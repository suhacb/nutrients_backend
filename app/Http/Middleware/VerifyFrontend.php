<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use App\Services\Auth\AuthService;
use Illuminate\Http\Client\RequestException;
use Symfony\Component\HttpFoundation\Response;

class VerifyFrontend
{
    public function __construct(private AuthService $service) {}
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        logger()->info('ValidateFrontend');
        $accessToken = $request->bearerToken();
        $appName = $request->header('X-Application-Name');
        $appUrl = $request->header('X-Client-Url');
        $refreshToken = $request->header('X-Refresh-Token');

        // If any are missing, return 401
        if (!$accessToken || !$appName || !$appUrl || !$refreshToken) {
            return response()->json([
                'error' => 'Unauthenticated'
            ], 401);
        }

        try {
            $response = $this->service->validate($accessToken, $refreshToken, $appName, $appUrl);

            if ($response->successful()) {
                $responseData = $response->json();
                if($responseData == true) { return response()->json("true"); }
                if($responseData === false) { return response()->json("false"); }
                return response()->json($responseData);
            }
            return response()->json("false");
        } catch (RequestException $e) {
            logger()->error('Token validation HTTP error', ['exception' => $e]);
            return response()->json(['error' => 'Token validation service unavailable'], 503);
        } catch (Exception $e) {
            return response()->json($e->getMessage() ?? ['error' => 'Server error'], $e->getCode() ?: 400);
        }

        return $next($request);
    }
}
