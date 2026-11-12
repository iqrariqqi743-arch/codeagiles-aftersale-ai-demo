<?php

use App\Http\Middleware\CheckRegistrationEnabled;
use App\Http\Middleware\SuperAdminMiddleware;
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
            // Super Admin Middleware
            'super_admin' => SuperAdminMiddleware::class,

            // Owner Active Subscription Middleware
            'active.subscription' => \App\Http\Middleware\EnsureActiveSubscription::class,
            // Registration Enabled Middleware
            'registration.enabled' => CheckRegistrationEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
