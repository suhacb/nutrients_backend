<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class HandleTestMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('app.test_mode') || $request->header('X-Test-Mode') !== 'true') {
            return $next($request);
        }

        DB::beginTransaction();
        $response = $next($request);
        DB::rollBack();

        return $response;
    }
}
