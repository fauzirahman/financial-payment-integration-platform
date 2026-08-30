<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {
    }

    public function store(
        StorePaymentRequest $request
    ): JsonResponse {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (!$idempotencyKey) {
            return response()->json([
                'success' => false,
                'message' => 'Idempotency-Key header is required.',
            ], 400);
        }

        $result = $this->paymentService->create(
            $request->validated(),
            $idempotencyKey
        );

        return response()->json([
            'success' => true,
            'message' => $result['replayed']
                ? 'Payment response replayed successfully.'
                : 'Payment processed successfully.',
            'data' => $result['payment'],
        ], $result['replayed'] ? 200 : 201);
    }
}
