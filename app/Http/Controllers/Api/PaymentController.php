<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Services\Payment\PaymentQueryService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PaymentQueryService $paymentQueryService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $payments = $this->paymentQueryService->list(
            $request->only([
                'status',
                'gateway',
                'customer_id',
                'payment_number',
                'search',
                'per_page',
            ])
        );

        return response()->json([
            'success' => true,
            'message' => 'Payments retrieved successfully.',
            'data' => $payments->items(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
                'from' => $payments->firstItem(),
                'to' => $payments->lastItem(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $payment = $this->paymentQueryService->find($id);

        return response()->json([
            'success' => true,
            'message' => 'Payment retrieved successfully.',
            'data' => $payment,
        ]);
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

        $isSuccessful = $result['payment']->status === Payment::STATUS_SUCCESS;

        return response()->json([
            'success' => $isSuccessful,
            'message' => $result['replayed']
                ? 'Payment response replayed successfully.'
                : ($isSuccessful
                    ? 'Payment processed successfully.'
                    : 'Payment failed.'),
            'data' => $result['payment'],
        ], $result['replayed'] ? 200 : ($isSuccessful ? 201 : 422));
    }
}
