<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'webhook.signature' => \App\Http\Middleware\VerifyWebhookSignature::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule
            ->command('app:dispatch-webhook-retries')
            ->everyMinute()
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (
            \App\Exceptions\IdempotencyConflictException $exception
        ) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 409);
        });
    })
    ->create();
