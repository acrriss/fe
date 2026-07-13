<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Sri\Exceptions\DatoInvalido;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi();

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Cada panel tiene su propio login: los invitados de las rutas de
        // partner van al suyo, el resto al del panel de contribuyentes.
        $middleware->redirectGuestsTo(fn (Request $request): string => $request->routeIs('partner.*')
            ? route('partner.login')
            : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // La API siempre responde JSON, pida lo que pida el cliente.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        // Un dato que viola la ficha del SRI es un error del payload (422),
        // no un error del servidor.
        $exceptions->render(function (DatoInvalido $excepcion, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $excepcion->getMessage(),
                    'errors' => ['comprobante' => [$excepcion->getMessage()]],
                ], 422);
            }

            return null;
        });
    })->create();
