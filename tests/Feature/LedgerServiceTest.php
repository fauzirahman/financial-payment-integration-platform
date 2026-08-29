<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\FinancialTransaction;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChartOfAccount $bank;

    private ChartOfAccount $receivable;

    protected function setUp(): void
    {
        parent::setUp();

        $assets = ChartOfAccount::create([
            'code' => '1000',
            'name' => 'Assets',
            'type' => 'ASSET',
            'normal_balance' => 'DEBIT',
            'is_active' => true,
        ]);

        $this->bank = ChartOfAccount::create([
            'code' => '1100',
            'name' => 'Bank',
            'type' => 'ASSET',
            'normal_balance' => 'DEBIT',
            'parent_id' => $assets->id,
            'is_active' => true,
        ]);

        $this->receivable = ChartOfAccount::create([
            'code' => '1200',
            'name' => 'Accounts Receivable',
            'type' => 'ASSET',
            'normal_balance' => 'DEBIT',
            'parent_id' => $assets->id,
            'is_active' => true,
        ]);
    }

    public function test_it_creates_a_balanced_transaction(): void
    {
        $transaction = app(LedgerService::class)->create([
            'transaction_number' => 'TXN-TEST-001',
            'type' => 'PAYMENT',
            'transaction_date' => now(),
            'currency' => 'IDR',

            'entries' => [
                [
                    'chart_of_account_id' => $this->bank->id,
                    'debit' => '500000.00',
                    'credit' => '0.00',
                ],
                [
                    'chart_of_account_id' => $this->receivable->id,
                    'debit' => '0.00',
                    'credit' => '500000.00',
                ],
            ],
        ]);

        $this->assertDatabaseHas(
            'financial_transactions',
            [
                'id' => $transaction->id,
                'transaction_number' => 'TXN-TEST-001',
                'status' => 'POSTED',
            ]
        );

        $this->assertDatabaseCount('ledger_entries', 2);
    }

    public function test_it_rejects_unbalanced_transaction(): void
    {
        $this->expectException(RuntimeException::class);

        app(LedgerService::class)->create([
            'transaction_number' => 'TXN-TEST-002',
            'type' => 'PAYMENT',
            'transaction_date' => now(),
            'currency' => 'IDR',

            'entries' => [
                [
                    'chart_of_account_id' => $this->bank->id,
                    'debit' => '500000.00',
                    'credit' => '0.00',
                ],
                [
                    'chart_of_account_id' => $this->receivable->id,
                    'debit' => '0.00',
                    'credit' => '450000.00',
                ],
            ],
        ]);
    }

    public function test_unbalanced_transaction_is_rolled_back(): void
    {
        try {
            app(LedgerService::class)->create([
                'transaction_number' => 'TXN-TEST-003',
                'type' => 'PAYMENT',
                'transaction_date' => now(),
                'currency' => 'IDR',

                'entries' => [
                    [
                        'chart_of_account_id' => $this->bank->id,
                        'debit' => '500000.00',
                        'credit' => '0.00',
                    ],
                    [
                        'chart_of_account_id' => $this->receivable->id,
                        'debit' => '0.00',
                        'credit' => '450000.00',
                    ],
                ],
            ]);
        } catch (RuntimeException) {
            // Expected.
        }

        $this->assertDatabaseMissing(
            'financial_transactions',
            [
                'transaction_number' => 'TXN-TEST-003',
            ]
        );

        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_it_rejects_duplicate_transaction_number(): void
    {
        $service = app(LedgerService::class);

        $payload = [
            'transaction_number' => 'TXN-TEST-004',
            'type' => 'PAYMENT',
            'transaction_date' => now(),
            'currency' => 'IDR',

            'entries' => [
                [
                    'chart_of_account_id' => $this->bank->id,
                    'debit' => '100000.00',
                    'credit' => '0.00',
                ],
                [
                    'chart_of_account_id' => $this->receivable->id,
                    'debit' => '0.00',
                    'credit' => '100000.00',
                ],
            ],
        ];

        $service->create($payload);

        $this->expectException(InvalidArgumentException::class);

        $service->create($payload);
    }

    public function test_it_rejects_entry_with_both_debit_and_credit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(LedgerService::class)->create([
            'transaction_number' => 'TXN-TEST-005',
            'type' => 'PAYMENT',
            'transaction_date' => now(),
            'currency' => 'IDR',

            'entries' => [
                [
                    'chart_of_account_id' => $this->bank->id,
                    'debit' => '100000.00',
                    'credit' => '100000.00',
                ],
                [
                    'chart_of_account_id' => $this->receivable->id,
                    'debit' => '0.00',
                    'credit' => '0.00',
                ],
            ],
        ]);
    }

    public function test_it_rejects_zero_amount_entry(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(LedgerService::class)->create([
            'transaction_number' => 'TXN-TEST-006',
            'type' => 'PAYMENT',
            'transaction_date' => now(),
            'currency' => 'IDR',

            'entries' => [
                [
                    'chart_of_account_id' => $this->bank->id,
                    'debit' => '0.00',
                    'credit' => '0.00',
                ],
                [
                    'chart_of_account_id' => $this->receivable->id,
                    'debit' => '0.00',
                    'credit' => '0.00',
                ],
            ],
        ]);
    }
}