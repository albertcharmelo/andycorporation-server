<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\HandleAppearance;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
        ]);

        // IMPORTANTE: NO excluir broadcasting/auth de CSRF
        // Pusher necesita que se verifique el CSRF token para autenticación de sesión web
        $middleware->validateCsrfTokens(except: [
            // Solo excluimos las rutas de API que usan tokens Bearer
            // 'api/orders/*/chat', // Comentado - estas rutas ahora están en web.php con middleware web
            // 'broadcasting/auth', // REMOVIDO - Pusher necesita CSRF
        ]);

        $middleware->alias([
            'role' => CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
