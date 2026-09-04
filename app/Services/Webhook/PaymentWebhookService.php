<?php

namespace App\Services\Webhook;

use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Services\Payment\PaymentLedgerService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentWebhookService
{
    public function __construct(
        private readonly WebhookRetryService $retryService,
        private readonly PaymentLedgerService $paymentLedgerService,
    ) {
    }

    public function process(array $payload): PaymentWebhookEvent
    {
        $eventId = $payload['event_id'];
        $event = $this->findOrCreateEvent($payload, $eventId);

        if ($event->status === 'PROCESSED') {
            return $event;
        }

        if ($event->status === 'PERMANENTLY_FAILED') {
            return $event;
        }

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

                $eventType = $payload['event_type'];

                if ($eventType === 'payment.succeeded') {
                    $wasPending = $payment->status === Payment::STATUS_PENDING;

                    $payment->transitionTo(Payment::STATUS_SUCCESS);
                    $payment->paid_at = $payment->paid_at ?? now();
                    $payment->save();

                    // A webhook may be the source of truth for completion.
                    // In that case the accounting entry must also be posted.
                    if ($wasPending) {
                        $this->paymentLedgerService->postSuccessfulPayment(
                            $payment->fresh()
                        );
                    }
                } elseif ($eventType === 'payment.failed') {
                    $payment->transitionTo(Payment::STATUS_FAILED);
                    $payment->save();
                } else {
                    throw new RuntimeException(
                        "Unsupported webhook event type: {$eventType}."
                    );
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

            $this->retryService->scheduleRetry($event);

            throw $exception;
        }
    }

    private function findOrCreateEvent(array $payload, string $eventId): PaymentWebhookEvent
    {
        $existing = PaymentWebhookEvent::query()
            ->where('event_id', $eventId)
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            return PaymentWebhookEvent::create([
                'event_id' => $eventId,
                'event_type' => $payload['event_type'],
                'gateway' => $payload['gateway'],
                'gateway_transaction_id' => $payload['gateway_transaction_id'],
                'payload' => $payload,
                'status' => 'RECEIVED',
                'attempts' => 0,
                'max_attempts' => 5,
            ]);
        } catch (UniqueConstraintViolationException) {
            return PaymentWebhookEvent::query()
                ->where('event_id', $eventId)
                ->firstOrFail();
        }
    }
}
