<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Forzar respuestas JSON en cualquier ruta /api/* — sin esto, errores
        // 404/500/422/etc. devuelven HTML según el header Accept del cliente.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // 404 limpio. Dos casos distintos:
        //  - URL no matchea ninguna ruta (`$request->route()` es null) → genérico.
        //  - El controller llamó abort(404, 'X') → respetamos 'X'.
        // Sin esto, el default de Laravel devolvería "The route foo could not
        // be found" o leakearía "No query results for model App\Models\Product".
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            $message = $request->route() === null
                ? 'Resource not found'
                : ($e->getMessage() ?: 'Resource not found');

            return response()->json(['message' => $message], 404);
        });
    })->create();
