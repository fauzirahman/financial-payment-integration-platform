<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'Financial Payment Integration API is running.',
    ]);
});

Route::post('/payments', [
    PaymentController::class,
    'store',
]);

Route::post('/webhooks/mock-payment', [
    PaymentWebhookController::class,
    'handle',
]);