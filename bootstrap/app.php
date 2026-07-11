<?php

use Sentry\Laravel\Integration;
use App\Http\Middleware\IsClubAdmin;
use App\Http\Middleware\IsSuperAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'superadmin'      => \App\Http\Middleware\IsSuperAdmin::class,
            'clubadmin'       => \App\Http\Middleware\IsClubAdmin::class,
            'permission'        => \App\Http\Middleware\CheckClubRole::class,
            'verified'        => \App\Http\Middleware\EnsureEmailVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);

        $exceptions->render(function (ThrottleRequestsException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'Trop de tentatives. Merci de réessayer dans un instant.',
            ], 429, $e->getHeaders());
        });
    })->create();
$app->register(\Barryvdh\DomPDF\ServiceProvider::class);
