<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'financial_transaction_id',
        'chart_of_account_id',
        'debit',
        'credit',
        'currency',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(
            FinancialTransaction::class
        );
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(
            ChartOfAccount::class
        );
    }
}