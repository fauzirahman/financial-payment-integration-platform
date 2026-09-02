<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Payment extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_SUCCESS = 'SUCCESS';

    public const STATUS_FAILED = 'FAILED';

    protected $fillable = [
        'payment_number',
        'customer_id',
        'amount',
        'currency',
        'method',
        'status',
        'gateway',
        'gateway_transaction_id',
        'paid_at',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transitionTo(string $status): void
    {
        $currentStatus = $this->status;

        /*
         * Same-state transition is intentionally idempotent.
         *
         * Example:
         * SUCCESS -> SUCCESS
         * FAILED  -> FAILED
         */
        if ($currentStatus === $status) {
            return;
        }

        $allowedTransitions = [
            self::STATUS_PENDING => [
                self::STATUS_SUCCESS,
                self::STATUS_FAILED,
            ],
            self::STATUS_SUCCESS => [],
            self::STATUS_FAILED => [],
        ];

        if (!in_array(
            $status,
            $allowedTransitions[$currentStatus] ?? [],
            true
        )) {
            throw new LogicException(
                "Invalid payment state transition: {$currentStatus} -> {$status}."
            );
        }

        $this->status = $status;
    }
}