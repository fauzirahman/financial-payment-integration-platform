<?php

namespace App\Contracts;

use App\Models\Payment;

interface PaymentGatewayInterface
{
    /**
     * Process a payment through the external provider.
     *
     * @return array{
     *     success: bool,
     *     status: string,
     *     gateway_transaction_id: string|null,
     *     message: string
     * }
     */
    public function charge(Payment $payment): array;
}