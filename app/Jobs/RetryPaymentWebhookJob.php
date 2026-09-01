<?php

namespace App\Jobs;

use App\Models\PaymentWebhookEvent;
use App\Services\Webhook\PaymentWebhookService;
use App\Services\Webhook\WebhookRetryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryPaymentWebhookJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public string $webhookEventId,
    ) {
    }

    public function handle(
        WebhookRetryService $retryService,
        PaymentWebhookService $webhookService,
    ): void {
        $event = PaymentWebhookEvent::query()
            ->find($this->webhookEventId);

        if (!$event) {
            return;
        }

        if ($event->status === 'PROCESSED') {
            return;
        }

        if ($event->status === 'PERMANENTLY_FAILED') {
            return;
        }

        if (!$retryService->canRetry($event)) {
            return;
        }

        $webhookService->process($event->payload);
    }
}

