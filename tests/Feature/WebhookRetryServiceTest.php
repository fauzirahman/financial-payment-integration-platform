<?php

namespace Tests\Feature;

use App\Models\PaymentWebhookEvent;
use App\Services\Webhook\WebhookRetryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookRetryServiceTest extends TestCase
{
    use RefreshDatabase;

    private WebhookRetryService $retryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->retryService = app(WebhookRetryService::class);
    }

    public function test_first_failed_attempt_schedules_one_minute_retry(): void
    {
        Carbon::setTestNow('2026-08-30 23:00:00');

        $event = PaymentWebhookEvent::create([
            'event_id' => 'evt-retry-001',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'tx-retry-001',
            'payload' => [],
            'status' => 'FAILED',
            'attempts' => 0,
            'max_attempts' => 5,
        ]);

        $result = $this->retryService->scheduleRetry($event);

        $this->assertSame(1, $result->attempts);
        $this->assertSame('FAILED', $result->status);
        $this->assertTrue(
            $result->next_retry_at->equalTo(
                Carbon::parse('2026-08-30 23:01:00')
            )
        );
    }

    public function test_second_failed_attempt_schedules_five_minute_retry(): void
    {
        Carbon::setTestNow('2026-08-30 23:00:00');

        $event = PaymentWebhookEvent::create([
            'event_id' => 'evt-retry-002',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'tx-retry-002',
            'payload' => [],
            'status' => 'FAILED',
            'attempts' => 1,
            'max_attempts' => 5,
        ]);

        $result = $this->retryService->scheduleRetry($event);

        $this->assertSame(2, $result->attempts);
        $this->assertSame('FAILED', $result->status);
        $this->assertTrue(
            $result->next_retry_at->equalTo(
                Carbon::parse('2026-08-30 23:05:00')
            )
        );
    }

    public function test_third_failed_attempt_schedules_fifteen_minute_retry(): void
    {
        Carbon::setTestNow('2026-08-30 23:00:00');

        $event = PaymentWebhookEvent::create([
            'event_id' => 'evt-retry-003',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'tx-retry-003',
            'payload' => [],
            'status' => 'FAILED',
            'attempts' => 2,
            'max_attempts' => 5,
        ]);

        $result = $this->retryService->scheduleRetry($event);

        $this->assertSame(3, $result->attempts);
        $this->assertSame('FAILED', $result->status);
        $this->assertTrue(
            $result->next_retry_at->equalTo(
                Carbon::parse('2026-08-30 23:15:00')
            )
        );
    }

    public function test_fourth_failed_attempt_schedules_thirty_minute_retry(): void
    {
        Carbon::setTestNow('2026-08-30 23:00:00');

        $event = PaymentWebhookEvent::create([
            'event_id' => 'evt-retry-004',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'tx-retry-004',
            'payload' => [],
            'status' => 'FAILED',
            'attempts' => 3,
            'max_attempts' => 5,
        ]);

        $result = $this->retryService->scheduleRetry($event);

        $this->assertSame(4, $result->attempts);
        $this->assertSame('FAILED', $result->status);
        $this->assertTrue(
            $result->next_retry_at->equalTo(
                Carbon::parse('2026-08-30 23:30:00')
            )
        );
    }

    public function test_fifth_failed_attempt_becomes_permanently_failed(): void
    {
        Carbon::setTestNow('2026-08-30 23:00:00');

        $event = PaymentWebhookEvent::create([
            'event_id' => 'evt-retry-005',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'tx-retry-005',
            'payload' => [],
            'status' => 'FAILED',
            'attempts' => 4,
            'max_attempts' => 5,
        ]);

        $result = $this->retryService->scheduleRetry($event);

        $this->assertSame(5, $result->attempts);
        $this->assertSame('PERMANENTLY_FAILED', $result->status);
        $this->assertNull($result->next_retry_at);
    }

    public function test_failed_event_can_retry_after_scheduled_time(): void
    {
        Carbon::setTestNow('2026-08-30 23:05:01');

        $event = PaymentWebhookEvent::create([
            'event_id' => 'evt-retry-006',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'tx-retry-006',
            'payload' => [],
            'status' => 'FAILED',
            'attempts' => 2,
            'max_attempts' => 5,
            'next_retry_at' => '2026-08-30 23:05:00',
        ]);

        $this->assertTrue(
            $this->retryService->canRetry($event)
        );
    }

    public function test_failed_event_cannot_retry_before_scheduled_time(): void
    {
        Carbon::setTestNow('2026-08-30 23:04:59');

        $event = PaymentWebhookEvent::create([
            'event_id' => 'evt-retry-007',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'tx-retry-007',
            'payload' => [],
            'status' => 'FAILED',
            'attempts' => 2,
            'max_attempts' => 5,
            'next_retry_at' => '2026-08-30 23:05:00',
        ]);

        $this->assertFalse(
            $this->retryService->canRetry($event)
        );
    }

    public function test_permanently_failed_event_cannot_retry(): void
    {
        Carbon::setTestNow('2026-08-30 23:10:00');

        $event = PaymentWebhookEvent::create([
            'event_id' => 'evt-retry-008',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'tx-retry-008',
            'payload' => [],
            'status' => 'PERMANENTLY_FAILED',
            'attempts' => 5,
            'max_attempts' => 5,
            'next_retry_at' => null,
        ]);

        $this->assertFalse(
            $this->retryService->canRetry($event)
        );
    }
}
