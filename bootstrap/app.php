<?php

use Sentry\Laravel\Integration;
use App\Http\Middleware\IsClubAdmin;
use App\Http\Middleware\IsSuperAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);
    })->create();
$app->register(\Barryvdh\DomPDF\ServiceProvider::class);
