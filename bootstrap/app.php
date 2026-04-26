<?php

use App\Exceptions\NutrientAttachedException;
use App\Exceptions\NutrientHasChildrenException;
use App\Http\Middleware\EnsureUserFromToken;
use App\Http\Middleware\VerifyFrontend;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'verify.frontend' => VerifyFrontend::class,
            'ensure.user.from.token' => EnsureUserFromToken::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NutrientHasChildrenException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        });
        $exceptions->render(function (NutrientAttachedException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        });
    })->create();
