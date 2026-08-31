<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'event_id',
        'event_type',
        'gateway',
        'gateway_transaction_id',
        'payload',
        'status',
        'attempts',
        'max_attempts',
        'processed_at',
        'next_retry_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'processed_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }
}