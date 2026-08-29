<?php

namespace App\Services\Webhook;

use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentWebhookService
{
    public function process(array $payload): PaymentWebhookEvent
    {
        return DB::transaction(function () use ($payload) {
            $eventId = $payload['event_id'];

            $existing = PaymentWebhookEvent::query()
                ->where('event_id', $eventId)
                ->first();

            if ($existing) {
                return $existing;
            }

            $event = PaymentWebhookEvent::create([
                'event_id' => $eventId,
                'event_type' => $payload['event_type'],
                'gateway' => $payload['gateway'],
                'gateway_transaction_id' =>
                    $payload['gateway_transaction_id'],
                'payload' => $payload,
                'status' => 'RECEIVED',
            ]);

            $payment = Payment::query()
                ->where(
                    'gateway_transaction_id',
                    $payload['gateway_transaction_id']
                )
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                $event->update([
                    'status' => 'FAILED',
                    'error_message' =>
                        'Payment was not found.',
                ]);

                throw new RuntimeException(
                    'Payment not found for webhook event.'
                );
            }

            if ($payload['event_type'] === 'payment.succeeded') {
                $payment->update([
                    'status' => 'SUCCESS',
                    'paid_at' => $payment->paid_at ?? now(),
                ]);
            }

            $event->update([
                'status' => 'PROCESSED',
                'processed_at' => now(),
            ]);

            return $event->fresh();
        });
    }
}