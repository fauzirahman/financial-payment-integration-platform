<?php

namespace Tests\Feature;

use App\Models\PaymentWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_events_can_be_listed(): void
    {
        PaymentWebhookEvent::create([
            'event_id' => 'evt-query-001',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'tx-query-001',
            'payload' => [
                'event_id' => 'evt-query-001',
            ],
            'status' => 'PROCESSED',
            'attempts' => 0,
            'max_attempts' => 5,
            'processed_at' => now(),
        ]);

        PaymentWebhookEvent::create([
            'event_id' => 'evt-query-002',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'tx-query-002',
            'payload' => [
                'event_id' => 'evt-query-002',
            ],
            'status' => 'FAILED',
            'attempts' => 1,
            'max_attempts' => 5,
            'next_retry_at' => now()->addMinute(),
            'error_message' => 'Payment not found.',
        ]);

        $response = $this->getJson('/api/webhooks');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_webhook_events_can_be_filtered_by_status(): void
    {
        PaymentWebhookEvent::create([
            'event_id' => 'evt-query-003',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'tx-query-003',
            'payload' => [],
            'status' => 'PROCESSED',
            'attempts' => 0,
            'max_attempts' => 5,
            'processed_at' => now(),
        ]);

        PaymentWebhookEvent::create([
            'event_id' => 'evt-query-004',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'tx-query-004',
            'payload' => [],
            'status' => 'FAILED',
            'attempts' => 2,
            'max_attempts' => 5,
            'next_retry_at' => now()->addMinutes(5),
        ]);

        $response = $this->getJson(
            '/api/webhooks?status=FAILED'
        );

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath(
                'data.0.event_id',
                'evt-query-004'
            )
            ->assertJsonPath(
                'data.0.status',
                'FAILED'
            );
    }

    public function test_webhook_detail_can_be_retrieved(): void
    {
        $event = PaymentWebhookEvent::create([
            'event_id' => 'evt-query-005',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'tx-query-005',
            'payload' => [
                'event_id' => 'evt-query-005',
                'source' => 'mock-gateway',
            ],
            'status' => 'FAILED',
            'attempts' => 2,
            'max_attempts' => 5,
            'next_retry_at' => now()->addMinutes(5),
            'error_message' => 'Payment not found.',
        ]);

        $response = $this->getJson(
            "/api/webhooks/{$event->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.id',
                $event->id
            )
            ->assertJsonPath(
                'data.event_id',
                'evt-query-005'
            )
            ->assertJsonPath(
                'data.status',
                'FAILED'
            )
            ->assertJsonPath(
                'data.attempts',
                2
            )
            ->assertJsonPath(
                'data.max_attempts',
                5
            )
            ->assertJsonPath(
                'data.error_message',
                'Payment not found.'
            );
    }

    public function test_unknown_webhook_returns_not_found(): void
    {
        $response = $this->getJson(
            '/api/webhooks/00000000-0000-0000-0000-000000000000'
        );

        $response->assertNotFound();
    }

    public function test_webhook_list_supports_pagination(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            PaymentWebhookEvent::create([
                'event_id' => sprintf(
                    'evt-page-%03d',
                    $i
                ),
                'event_type' => 'payment.succeeded',
                'gateway' => 'mock',
                'gateway_transaction_id' => sprintf(
                    'tx-page-%03d',
                    $i
                ),
                'payload' => [],
                'status' => 'PROCESSED',
                'attempts' => 0,
                'max_attempts' => 5,
                'processed_at' => now(),
            ]);
        }

        $response = $this->getJson(
            '/api/webhooks?per_page=2'
        );

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(2, 'data');
    }
}
