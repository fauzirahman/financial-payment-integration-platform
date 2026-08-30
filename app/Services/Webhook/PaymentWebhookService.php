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
        $eventId = $payload['event_id'];

        $existing = PaymentWebhookEvent::query()
            ->where('event_id', $eventId)
            ->first();

        /*
         * A successfully processed event is fully idempotent.
         * Do not process it again.
         */
        if ($existing?->status === 'PROCESSED') {
            return $existing;
        }

        /*
         * Create the event outside the processing transaction.
         *
         * This is important because a failed webhook must remain
         * persisted for audit, troubleshooting, and future retry.
         */
        $event = $existing ?? PaymentWebhookEvent::create([
            'event_id' => $eventId,
            'event_type' => $payload['event_type'],
            'gateway' => $payload['gateway'],
            'gateway_transaction_id' => $payload['gateway_transaction_id'],
            'payload' => $payload,
            'status' => 'RECEIVED',
        ]);

        try {
            $processedEvent = DB::transaction(function () use ($event, $payload) {
                $payment = Payment::query()
                    ->where(
                        'gateway_transaction_id',
                        $payload['gateway_transaction_id']
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$payment) {
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
                    'error_message' => null,
                ]);

                return $event->fresh();
            });

            return $processedEvent;
        } catch (\Throwable $exception) {
            /*
             * This update is intentionally outside the transaction above.
             * Therefore FAILED events are not rolled back.
             */
            $event->update([
                'status' => 'FAILED',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
