<?php

namespace App\Services;

use App\Models\FinancialTransaction;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class LedgerService
{
    /**
     * Create a balanced double-entry financial transaction.
     *
     * @param array{
     *     transaction_number: string,
     *     type: string,
     *     transaction_date: mixed,
     *     currency: string,
     *     description?: string|null,
     *     reference_type?: string|null,
     *     reference_id?: string|null,
     *     status?: string,
     *     entries: array<int, array{
     *         chart_of_account_id: string,
     *         debit?: string|int|float,
     *         credit?: string|int|float,
     *         description?: string|null
     *     }>
     * } $data
     */
    public function create(array $data): FinancialTransaction
    {
        $this->validateTransaction($data);

        return DB::transaction(function () use ($data) {
            $this->ensureTransactionNumberIsUnique(
                $data['transaction_number']
            );

            $transaction = FinancialTransaction::create([
                'transaction_number' => $data['transaction_number'],
                'type' => $data['type'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'description' => $data['description'] ?? null,
                'transaction_date' => $data['transaction_date'],
                'status' => $data['status'] ?? 'POSTED',
            ]);

            foreach ($data['entries'] as $entry) {
                LedgerEntry::create([
                    'financial_transaction_id' => $transaction->id,
                    'chart_of_account_id' => $entry['chart_of_account_id'],
                    'debit' => $entry['debit'] ?? '0.00',
                    'credit' => $entry['credit'] ?? '0.00',
                    'currency' => $data['currency'],
                    'description' => $entry['description'] ?? null,
                ]);
            }

            $this->assertBalanced($transaction);

            return $transaction->load('ledgerEntries');
        });
    }

    /**
     * Validate the complete transaction payload.
     */
    private function validateTransaction(array $data): void
    {
        foreach ([
            'transaction_number',
            'type',
            'transaction_date',
            'currency',
            'entries',
        ] as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException(
                    "Missing required field: {$field}."
                );
            }
        }

        if (strlen($data['transaction_number']) > 40) {
            throw new InvalidArgumentException(
                'Transaction number must not exceed 40 characters.'
            );
        }

        if (!preg_match('/^[A-Z]{3}$/', $data['currency'])) {
            throw new InvalidArgumentException(
                'Currency must be a valid 3-letter uppercase code.'
            );
        }

        $this->validateEntries($data['entries']);
    }

    /**
     * Validate individual ledger entries.
     */
    private function validateEntries(array $entries): void
    {
        if (count($entries) < 2) {
            throw new InvalidArgumentException(
                'A financial transaction must contain at least two ledger entries.'
            );
        }

        foreach ($entries as $index => $entry) {
            if (!isset($entry['chart_of_account_id'])) {
                throw new InvalidArgumentException(
                    "Ledger entry {$index} is missing chart_of_account_id."
                );
            }

            $debit = $this->normalizeAmount($entry['debit'] ?? '0');
            $credit = $this->normalizeAmount($entry['credit'] ?? '0');

            if ($debit < 0 || $credit < 0) {
                throw new InvalidArgumentException(
                    "Ledger entry {$index} contains a negative amount."
                );
            }

            if ($debit !== '0.00' && $credit !== '0.00') {
                throw new InvalidArgumentException(
                    "Ledger entry {$index} cannot contain both debit and credit."
                );
            }

            if ($debit === '0.00' && $credit === '0.00') {
                throw new InvalidArgumentException(
                    "Ledger entry {$index} must contain either debit or credit."
                );
            }
        }
    }

    /**
     * Ensure transaction number has not already been used.
     */
    private function ensureTransactionNumberIsUnique(
        string $transactionNumber
    ): void {
        if (
            FinancialTransaction::query()
                ->where('transaction_number', $transactionNumber)
                ->exists()
        ) {
            throw new InvalidArgumentException(
                "Transaction number {$transactionNumber} already exists."
            );
        }
    }

    /**
     * Ensure debit equals credit.
     */
    private function assertBalanced(
        FinancialTransaction $transaction
    ): void {
        $totals = $transaction->ledgerEntries()
            ->selectRaw(
                'COALESCE(SUM(debit), 0) AS total_debit,
                 COALESCE(SUM(credit), 0) AS total_credit'
            )
            ->first();

        $totalDebit = $this->normalizeAmount($totals->total_debit);
        $totalCredit = $this->normalizeAmount($totals->total_credit);

        if ($totalDebit !== $totalCredit) {
            throw new RuntimeException(
                sprintf(
                    'Ledger transaction is not balanced. Debit: %s, Credit: %s.',
                    $totalDebit,
                    $totalCredit
                )
            );
        }
    }

    /**
     * Normalize monetary amount to two decimal places.
     */
    private function normalizeAmount(string|int|float $amount): string
    {
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException(
                'Ledger amount must be numeric.'
            );
        }

        $amount = (string) $amount;

        if (!preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException(
                'Ledger amount must be a non-negative monetary value with maximum 2 decimal places.'
            );
        }

        return number_format((float) $amount, 2, '.', '');
    }
}