<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {
    }

    public function store(
        StorePaymentRequest $request
    ): JsonResponse {
        $idempotencyKey = $request->header(
            'Idempotency-Key'
        );

        if (!$idempotencyKey) {
            return response()->json([
                'success' => false,
                'message' => 'Idempotency-Key header is required.',
            ], 400);
        }

        $payment = $this->paymentService->create(
            $request->validated(),
            $idempotencyKey
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment processed successfully.',
            'data' => $payment,
        ], 201);
    }
}