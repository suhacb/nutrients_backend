<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTestMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('app.test_mode')) {
            abort(404);
        }

        return $next($request);
    }
}
