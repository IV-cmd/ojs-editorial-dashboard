<?php

use App\Http\Controllers\MetricsController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Outside the web group: a Prometheus scrape needs no session, cookies, or CSRF.
        then: function () {
            Route::get('/metrics', MetricsController::class);
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // There is no login page; API guests get a 401 instead of a redirect.
        $middleware->redirectGuestsTo(fn (Request $request): ?string => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API clients always get JSON errors (401/403/404/422), never an HTML page or a login redirect.
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('api/*') || $request->expectsJson());
    })->create();
