<?php

namespace App\Console\Commands;

use App\Jobs\RetryPaymentWebhookJob;
use App\Models\PaymentWebhookEvent;
use Illuminate\Console\Command;

class DispatchWebhookRetries extends Command
{
    protected $signature = 'app:dispatch-webhook-retries';

    protected $description = 'Dispatch due payment webhook retries to the queue';

    public function handle(): int
    {
        $dispatched = 0;

        PaymentWebhookEvent::query()
            ->where('status', 'FAILED')
            ->whereColumn('attempts', '<', 'max_attempts')
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->chunkById(100, function ($events) use (&$dispatched): void {
                foreach ($events as $event) {
                    RetryPaymentWebhookJob::dispatch($event->id);

                    $dispatched++;
                }
            });

        $this->info(
            "Dispatched {$dispatched} webhook retry job(s)."
        );

        return self::SUCCESS;
    }
}
