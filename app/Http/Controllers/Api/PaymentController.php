<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (!$idempotencyKey) {
            return response()->json([
                'success' => false,
                'message' => 'Idempotency-Key header is required.',
            ], 400);
        }

        try {
            $result = $this->paymentService->create(
                $request->validated(),
                $idempotencyKey
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 409);
        }

        $payment = $result['payment'];
        $status = $result['replayed'] ? 200 : ($payment->status === 'SUCCESS' ? 201 : 422);

        return response()->json([
            'success' => $payment->status === 'SUCCESS',
            'message' => $payment->status === 'SUCCESS'
                ? 'Payment processed successfully.'
                : 'Payment failed.',
            'data' => $payment,
        ], $status);
    }
}
