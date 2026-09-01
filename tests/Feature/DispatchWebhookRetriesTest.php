<?php

namespace Tests\Feature;

use App\Jobs\RetryPaymentWebhookJob;
use App\Models\PaymentWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchWebhookRetriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_failed_webhook_is_dispatched(): void
    {
        Queue::fake();

        $event = PaymentWebhookEvent::create([
            'event_id' => 'evt-due-001',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'txn-due-001',
            'payload' => [
                'event_id' => 'evt-due-001',
            ],
            'status' => 'FAILED',
            'attempts' => 1,
            'max_attempts' => 5,
            'next_retry_at' => now()->subMinute(),
            'error_message' => 'Temporary failure.',
        ]);

        $this->artisan('app:dispatch-webhook-retries')
            ->assertSuccessful()
            ->expectsOutput('Dispatched 1 webhook retry job(s).');

        Queue::assertPushed(
            RetryPaymentWebhookJob::class,
            function (RetryPaymentWebhookJob $job) use ($event): bool {
                return $job->webhookEventId === $event->id;
            }
        );
    }

    public function test_webhook_before_retry_time_is_not_dispatched(): void
    {
        Queue::fake();

        PaymentWebhookEvent::create([
            'event_id' => 'evt-future-001',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'txn-future-001',
            'payload' => [
                'event_id' => 'evt-future-001',
            ],
            'status' => 'FAILED',
            'attempts' => 1,
            'max_attempts' => 5,
            'next_retry_at' => now()->addMinutes(5),
            'error_message' => 'Temporary failure.',
        ]);

        $this->artisan('app:dispatch-webhook-retries')
            ->assertSuccessful()
            ->expectsOutput('Dispatched 0 webhook retry job(s).');

        Queue::assertNothingPushed();
    }

    public function test_permanently_failed_webhook_is_not_dispatched(): void
    {
        Queue::fake();

        PaymentWebhookEvent::create([
            'event_id' => 'evt-permanent-001',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'txn-permanent-001',
            'payload' => [
                'event_id' => 'evt-permanent-001',
            ],
            'status' => 'PERMANENTLY_FAILED',
            'attempts' => 5,
            'max_attempts' => 5,
            'next_retry_at' => null,
            'error_message' => 'Maximum retry attempts exceeded.',
        ]);

        $this->artisan('app:dispatch-webhook-retries')
            ->assertSuccessful()
            ->expectsOutput('Dispatched 0 webhook retry job(s).');

        Queue::assertNothingPushed();
    }

    public function test_webhook_reaching_max_attempts_is_not_dispatched(): void
    {
        Queue::fake();

        PaymentWebhookEvent::create([
            'event_id' => 'evt-max-001',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'txn-max-001',
            'payload' => [
                'event_id' => 'evt-max-001',
            ],
            'status' => 'FAILED',
            'attempts' => 5,
            'max_attempts' => 5,
            'next_retry_at' => now()->subMinute(),
            'error_message' => 'Maximum retry attempts reached.',
        ]);

        $this->artisan('app:dispatch-webhook-retries')
            ->assertSuccessful()
            ->expectsOutput('Dispatched 0 webhook retry job(s).');

        Queue::assertNothingPushed();
    }
}