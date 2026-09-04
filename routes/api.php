<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'Financial Payment Integration API is running.',
    ]);
});

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('/login', [
        AuthController::class,
        'login',
    ]);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [
            AuthController::class,
            'me',
        ]);

        Route::post('/logout', [
            AuthController::class,
            'logout',
        ]);
    });
});

/*
|--------------------------------------------------------------------------
| Protected Payment APIs
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/payments', [
        PaymentController::class,
        'index',
    ]);

    Route::get('/payments/{id}', [
        PaymentController::class,
        'show',
    ]);

    Route::post('/payments', [
        PaymentController::class,
        'store',
    ]);
});

/*
|--------------------------------------------------------------------------
| Protected Webhook Monitoring APIs
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/webhooks', [
        PaymentWebhookController::class,
        'index',
    ]);

    Route::get('/webhooks/{id}', [
        PaymentWebhookController::class,
        'show',
    ]);
});

/*
|--------------------------------------------------------------------------
| Public Webhook Callback
|--------------------------------------------------------------------------
|
| This simulates an external payment gateway callback.
| It does not use Sanctum authentication.
|
*/

Route::post('/webhooks/mock-payment', [
    PaymentWebhookController::class,
    'handle',
])->middleware('webhook.signature');