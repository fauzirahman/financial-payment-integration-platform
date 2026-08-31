<?php

namespace App\Services\Webhook;

use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentWebhookService
{
    public function __construct(
        private readonly WebhookRetryService $retryService,
    ) {
    }

    public function process(array $payload): PaymentWebhookEvent
    {
        $eventId = $payload['event_id'];

        $existing = PaymentWebhookEvent::query()
            ->where('event_id', $eventId)
            ->first();

        /*
         * Successfully processed events are fully idempotent.
         */
        if ($existing?->status === 'PROCESSED') {
            return $existing;
        }

        /*
         * Permanently failed events must not be processed again
         * automatically.
         */
        if ($existing?->status === 'PERMANENTLY_FAILED') {
            return $existing;
        }

        /*
         * Reuse an existing FAILED event during retry.
         * Otherwise create a new event.
         */
        $event = $existing ?? PaymentWebhookEvent::create([
            'event_id' => $eventId,
            'event_type' => $payload['event_type'],
            'gateway' => $payload['gateway'],
            'gateway_transaction_id' => $payload['gateway_transaction_id'],
            'payload' => $payload,
            'status' => 'RECEIVED',
            'attempts' => 0,
            'max_attempts' => 5,
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
                    'next_retry_at' => null,
                    'error_message' => null,
                ]);

                return $event->fresh();
            });

            return $processedEvent;
        } catch (\Throwable $exception) {
            $event->update([
                'status' => 'FAILED',
                'error_message' => $exception->getMessage(),
            ]);

            $event = $this->retryService->scheduleRetry($event);

            throw $exception;
        }
    }
}
