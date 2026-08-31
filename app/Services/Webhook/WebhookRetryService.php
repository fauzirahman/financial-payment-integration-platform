<?php

namespace App\Services\Webhook;

use App\Models\PaymentWebhookEvent;
use Carbon\Carbon;

class WebhookRetryService
{
    private const RETRY_DELAYS = [
        1 => 1,
        2 => 5,
        3 => 15,
        4 => 30,
    ];

    public function scheduleRetry(
        PaymentWebhookEvent $event
    ): PaymentWebhookEvent {
        $nextAttempt = $event->attempts + 1;

        if ($nextAttempt >= $event->max_attempts) {
            $event->update([
                'attempts' => $nextAttempt,
                'status' => 'PERMANENTLY_FAILED',
                'next_retry_at' => null,
            ]);

            return $event->fresh();
        }

        $delayMinutes = self::RETRY_DELAYS[$nextAttempt]
            ?? end(self::RETRY_DELAYS);

        $event->update([
            'attempts' => $nextAttempt,
            'status' => 'FAILED',
            'next_retry_at' => Carbon::now()->addMinutes($delayMinutes),
        ]);

        return $event->fresh();
    }

    public function canRetry(
        PaymentWebhookEvent $event
    ): bool {
        return $event->status === 'FAILED'
            && $event->attempts < $event->max_attempts
            && $event->next_retry_at !== null
            && $event->next_retry_at->isPast();
    }
}