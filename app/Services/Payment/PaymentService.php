<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Models\ChartOfAccount;
use App\Models\Payment;
use App\Services\Idempotency\IdempotencyService;
use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly LedgerService $ledgerService,
        private readonly IdempotencyService $idempotencyService,
    ) {}

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
            $payment = Payment::query()->findOrFail($idempotency->resource_id);

            return ['payment' => $payment, 'replayed' => true];
        }

        try {
            $payment = DB::transaction(function () use ($data): Payment {
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
                    $payment->update(['status' => 'FAILED']);

                    return $payment->fresh();
                }

                $payment->update([
                    'status' => 'SUCCESS',
                    'gateway_transaction_id' => $result['gateway_transaction_id'],
                    'paid_at' => now(),
                ]);

                $this->postSuccessfulPayment($payment->fresh());

                return $payment->fresh();
            });

            $body = [
                'success' => $payment->status === 'SUCCESS',
                'message' => $payment->status === 'SUCCESS'
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

            return ['payment' => $payment, 'replayed' => false];
        } catch (\Throwable $exception) {
            $this->idempotencyService->fail($idempotency, $exception->getMessage());

            throw $exception;
        }
    }

    private function postSuccessfulPayment(Payment $payment): void
    {
        $cash = ChartOfAccount::query()->where('code', '1100')->where('is_active', true)->first();
        $revenue = ChartOfAccount::query()->where('code', '4000')->where('is_active', true)->first();

        if (!$cash || !$revenue) {
            throw new InvalidArgumentException(
                'Default payment ledger accounts (1100 and 4000) are not configured.'
            );
        }

        $this->ledgerService->create([
            'transaction_number' => 'LEDGER-' . str_replace('-', '', Str::upper($payment->id)),
            'type' => 'PAYMENT',
            'transaction_date' => $payment->paid_at ?? now(),
            'currency' => $payment->currency,
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
            'description' => $payment->description ?? 'Customer payment',
            'status' => 'POSTED',
            'entries' => [
                [
                    'chart_of_account_id' => $cash->id,
                    'debit' => $payment->amount,
                    'credit' => '0.00',
                    'description' => 'Cash received from customer',
                ],
                [
                    'chart_of_account_id' => $revenue->id,
                    'debit' => '0.00',
                    'credit' => $payment->amount,
                    'description' => 'Payment revenue',
                ],
            ],
        ]);
    }

    private function validate(array $data): void
    {
        foreach (['payment_number', 'customer_id', 'amount', 'currency', 'method'] as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException("Missing required field: {$field}.");
            }
        }

        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        if (!preg_match('/^[A-Z]{3}$/', $data['currency'])) {
            throw new InvalidArgumentException('Currency must be a valid 3-letter uppercase code.');
        }
    }
}
