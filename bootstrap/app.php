<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\SanitizeInput::class,
            \App\Http\Middleware\SetLocaleFromSession::class,
            \App\Http\Middleware\EnsureRequiredEnvironmentIsConfigured::class,
            \App\Http\Middleware\AutoVerifyEmailForLocalTesting::class,
        ]);

        $middleware->alias([
            'ensure.company.context' => \App\Http\Middleware\EnsureCompanyContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! app()->isProduction()) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface) {
                return null;
            }

            return response()->view('errors.500', status: 500);
        });
    })->create();
