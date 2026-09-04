<?php

namespace App\Services\Payment;

use App\Models\ChartOfAccount;
use App\Models\Payment;
use App\Services\LedgerService;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentLedgerService
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {
    }

    /**
     * Post the accounting entry for a successful payment.
     *
     * The ledger transaction number is deterministic, so duplicate webhook
     * delivery cannot create a second accounting transaction for the same
     * payment.
     */
    public function postSuccessfulPayment(Payment $payment): void
    {
        $cash = ChartOfAccount::query()
            ->where('code', '1100')
            ->where('is_active', true)
            ->first();

        $revenue = ChartOfAccount::query()
            ->where('code', '4000')
            ->where('is_active', true)
            ->first();

        if (!$cash || !$revenue) {
            throw new InvalidArgumentException(
                'Default payment ledger accounts (1100 and 4000) are not configured.'
            );
        }

        $this->ledgerService->create([
            'transaction_number' => 'LEDGER-' . str_replace(
                '-',
                '',
                Str::upper($payment->id)
            ),
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
}
