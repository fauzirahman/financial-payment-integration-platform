<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\FinancialTransaction;
use App\Models\IdempotencyKey;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            User::where('email', 'seed.user1@example.com')->delete();

            $users = collect(range(1, 25))->map(
                fn (int $number): User => User::updateOrCreate(
                    ['email' => $number === 1 ? 'admin@example.com' : "seed.user{$number}@example.com"],
                    [
                        'name' => $number === 1 ? 'Finance Admin' : "Seed User {$number}",
                        'password' => 'password',
                        'email_verified_at' => now(),
                    ]
                )
            );

            $customers = collect(range(1, 25))->map(
                fn (int $number): Customer => Customer::updateOrCreate(
                    ['customer_number' => sprintf('SEED-CUST-%04d', $number)],
                    [
                        'name' => "Seed Customer {$number}",
                        'email' => "seed.customer{$number}@example.com",
                        'phone' => sprintf('+62812345%05d', $number),
                        'status' => $number % 10 === 0 ? 'INACTIVE' : 'ACTIVE',
                    ]
                )
            );

            foreach ($customers as $index => $customer) {
                Account::updateOrCreate(
                    ['account_number' => sprintf('SEED-ACC-%04d', $index + 1)],
                    [
                        'customer_id' => $customer->id,
                        'currency' => 'IDR',
                        'balance' => number_format(($index + 1) * 50000, 2, '.', ''),
                        'status' => 'ACTIVE',
                    ]
                );
            }

            $assets = ChartOfAccount::updateOrCreate(
                ['code' => '1000'],
                [
                    'name' => 'Assets',
                    'type' => 'ASSET',
                    'normal_balance' => 'DEBIT',
                    'is_active' => DB::raw('true'),
                ]
            );

            $chartAccounts = collect([
                ['code' => '1100', 'name' => 'Cash / Bank', 'type' => 'ASSET', 'normal_balance' => 'DEBIT', 'parent_id' => $assets->id],
                ['code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'ASSET', 'normal_balance' => 'DEBIT', 'parent_id' => $assets->id],
                ['code' => '4000', 'name' => 'Payment Revenue', 'type' => 'REVENUE', 'normal_balance' => 'CREDIT', 'parent_id' => null],
            ]);

            foreach (range(1, 21) as $number) {
                $chartAccounts->push([
                    'code' => sprintf('SEED-%04d', $number),
                    'name' => "Seed Account {$number}",
                    'type' => $number % 2 === 0 ? 'EXPENSE' : 'LIABILITY',
                    'normal_balance' => $number % 2 === 0 ? 'DEBIT' : 'CREDIT',
                    'parent_id' => null,
                ]);
            }

            $accounts = $chartAccounts->map(
                fn (array $account): ChartOfAccount => ChartOfAccount::updateOrCreate(
                    ['code' => $account['code']],
                    $account + ['is_active' => DB::raw('true')]
                )
            );

            $cash = $accounts->firstWhere('code', '1100');
            $revenue = $accounts->firstWhere('code', '4000');

            $payments = collect(range(1, 25))->map(
                fn (int $number): Payment => Payment::updateOrCreate(
                    ['payment_number' => sprintf('SEED-PAY-%04d', $number)],
                    [
                        'customer_id' => $customers[$number - 1]->id,
                        'amount' => number_format(100000 + ($number * 25000), 2, '.', ''),
                        'currency' => 'IDR',
                        'method' => ['CARD', 'BANK_TRANSFER', 'EWALLET'][$number % 3],
                        'status' => Payment::STATUS_SUCCESS,
                        'gateway' => 'mock',
                        'gateway_transaction_id' => sprintf('seed-tx-%04d', $number),
                        'paid_at' => now()->subDays(25 - $number),
                        'description' => "Seed payment {$number}",
                    ]
                )
            );

            $transactions = $payments->map(
                fn (Payment $payment): FinancialTransaction => FinancialTransaction::updateOrCreate(
                    ['transaction_number' => 'SEED-LEDGER-' . str_pad((string) $payments->search($payment) + 1, 4, '0', STR_PAD_LEFT)],
                    [
                        'type' => 'PAYMENT',
                        'reference_type' => Payment::class,
                        'reference_id' => $payment->id,
                        'description' => $payment->description,
                        'transaction_date' => $payment->paid_at,
                        'status' => 'POSTED',
                    ]
                )
            );

            LedgerEntry::whereIn('financial_transaction_id', $transactions->pluck('id'))->delete();
            foreach ($transactions as $index => $transaction) {
                $amount = $payments[$index]->amount;
                LedgerEntry::create([
                    'financial_transaction_id' => $transaction->id,
                    'chart_of_account_id' => $cash->id,
                    'debit' => $amount,
                    'credit' => '0.00',
                    'currency' => 'IDR',
                    'description' => 'Seed cash received',
                ]);
                LedgerEntry::create([
                    'financial_transaction_id' => $transaction->id,
                    'chart_of_account_id' => $revenue->id,
                    'debit' => '0.00',
                    'credit' => $amount,
                    'currency' => 'IDR',
                    'description' => 'Seed payment revenue',
                ]);
            }

            foreach ($payments as $index => $payment) {
                $payload = [
                    'event_id' => sprintf('seed-event-%04d', $index + 1),
                    'event_type' => 'payment.succeeded',
                    'gateway' => 'mock',
                    'gateway_transaction_id' => $payment->gateway_transaction_id,
                ];

                PaymentWebhookEvent::updateOrCreate(
                    ['event_id' => $payload['event_id']],
                    $payload + [
                        'payload' => $payload,
                        'status' => 'PROCESSED',
                        'attempts' => 1,
                        'max_attempts' => 5,
                        'processed_at' => $payment->paid_at,
                        'next_retry_at' => null,
                        'error_message' => null,
                    ]
                );

                IdempotencyKey::updateOrCreate(
                    ['key' => sprintf('seed-idempotency-%04d', $index + 1)],
                    [
                        'request_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
                        'resource_type' => Payment::class,
                        'resource_id' => $payment->id,
                        'response_status' => 201,
                        'response_body' => ['success' => true, 'payment_id' => $payment->id],
                        'status' => 'COMPLETED',
                    ]
                );
            }

        });
    }
}
