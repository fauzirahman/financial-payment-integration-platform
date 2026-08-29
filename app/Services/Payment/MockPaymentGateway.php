<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Payment;
use Illuminate\Support\Str;

class MockPaymentGateway implements PaymentGatewayInterface
{
    public function charge(Payment $payment): array
    {
        if ($payment->amount <= 0) {
            return [
                'success' => false,
                'status' => 'FAILED',
                'gateway_transaction_id' => null,
                'message' => 'Payment amount must be greater than zero.',
            ];
        }

        return [
            'success' => true,
            'status' => 'SUCCESS',
            'gateway_transaction_id' => 'MOCK-' . Str::upper(Str::random(16)),
            'message' => 'Payment successfully processed by mock gateway.',
        ];
    }
}