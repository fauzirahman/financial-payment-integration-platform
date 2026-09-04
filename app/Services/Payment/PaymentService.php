<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Payment;
use App\Services\Idempotency\IdempotencyService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly PaymentLedgerService $paymentLedgerService,
        private readonly IdempotencyService $idempotencyService,
    ) {
    }

    /**
     * @return array{payment: Payment, replayed: bool}
     */
    public function create(array $data, string $idempotencyKey): array
    {
        $this->validate($data);

        $idempotency = $this->idempotencyService->acquire(
            $idempotencyKey,
            $data,
            'payment'
        );

        if ($idempotency->status === 'COMPLETED') {
            $payment = Payment::query()->findOrFail(
                $idempotency->resource_id
            );

            return [
                'payment' => $payment,
                'replayed' => true,
            ];
        }

        try {
            $payment = DB::transaction(function () use ($data): Payment {
                $payment = Payment::create([
                    'payment_number' => $data['payment_number'],
                    'customer_id' => $data['customer_id'],
                    'amount' => $data['amount'],
                    'currency' => $data['currency'],
                    'method' => $data['method'],
                    'status' => Payment::STATUS_PENDING,
                    'gateway' => 'mock',
                    'description' => $data['description'] ?? null,
                ]);

                $result = $this->gateway->charge($payment);

                if (!$result['success']) {
                    $payment->transitionTo(Payment::STATUS_FAILED);
                    $payment->save();

                    return $payment->fresh();
                }

                $payment->transitionTo(Payment::STATUS_SUCCESS);

                $payment->gateway_transaction_id =
                    $result['gateway_transaction_id'];

                $payment->paid_at = now();

                $payment->save();

                $this->paymentLedgerService->postSuccessfulPayment($payment->fresh());

                return $payment->fresh();
            });

            $body = [
                'success' => $payment->status === Payment::STATUS_SUCCESS,
                'message' => $payment->status === Payment::STATUS_SUCCESS
                    ? 'Payment processed successfully.'
                    : 'Payment failed.',
                'data' => $payment,
            ];

            $this->idempotencyService->complete(
                $idempotency,
                201,
                $body,
                $payment->id
            );

            return [
                'payment' => $payment,
                'replayed' => false,
            ];
        } catch (\Throwable $exception) {
            $this->idempotencyService->fail(
                $idempotency,
                $exception->getMessage()
            );

            throw $exception;
        }
    }

    private function validate(array $data): void
    {
        foreach (
            [
                'payment_number',
                'customer_id',
                'amount',
                'currency',
                'method',
            ] as $field
        ) {
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