<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Payment;
use App\Services\Idempotency\IdempotencyService;
use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly LedgerService $ledgerService,
        private readonly IdempotencyService $idempotencyService,
    ) {}

    public function create(
        array $data,
        string $idempotencyKey
    ): Payment {
        $this->validate($data);

        return DB::transaction(function () use ($data) {
            $payment = Payment::create([
                'payment_number' => $data['payment_number'],
                'customer_id' => $data['customer_id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'method' => $data['method'],
                'status' => 'PENDING',
                'gateway' => 'mock',
                'description' => $data['description'] ?? null,
            ]);

            $result = $this->gateway->charge($payment);

            if (!$result['success']) {
                $payment->update([
                    'status' => 'FAILED',
                ]);

                return $payment->fresh();
            }

            $payment->update([
                'status' => 'SUCCESS',
                'gateway_transaction_id' =>
                    $result['gateway_transaction_id'],
                'paid_at' => now(),
            ]);

            return $payment->fresh();
        });
    }

    private function validate(array $data): void
    {
        foreach ([
            'payment_number',
            'customer_id',
            'amount',
            'currency',
            'method',
        ] as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException(
                    "Missing required field: {$field}."
                );
            }
        }

        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            throw new InvalidArgumentException(
                'Payment amount must be greater than zero.'
            );
        }

        if (!preg_match('/^[A-Z]{3}$/', $data['currency'])) {
            throw new InvalidArgumentException(
                'Currency must be a valid 3-letter uppercase code.'
            );
        }
    }
}
