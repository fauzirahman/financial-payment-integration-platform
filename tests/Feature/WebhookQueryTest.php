<?php

namespace Tests\Feature;

use App\Models\PaymentWebhookEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookQueryTest extends TestCase
{
    use RefreshDatabase;

    private function authenticate(): void
    {
        $user = User::factory()->create();

        $token = $user
            ->createToken('test-token')
            ->plainTextToken;

        $this->withToken($token);
    }

    public function test_webhook_events_can_be_listed(): void
    {
        $this->authenticate();

        PaymentWebhookEvent::create([
            'event_id' => 'evt-query-001',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'txn-query-001',
            'payload' => [
                'event_id' => 'evt-query-001',
                'event_type' => 'payment.succeeded',
            ],
            'status' => 'PROCESSED',
            'attempts' => 1,
            'max_attempts' => 5,
            'processed_at' => now(),
        ]);

        PaymentWebhookEvent::create([
            'event_id' => 'evt-query-002',
            'event_type' => 'payment.failed',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'txn-query-002',
            'payload' => [
                'event_id' => 'evt-query-002',
                'event_type' => 'payment.failed',
            ],
            'status' => 'FAILED',
            'attempts' => 1,
            'max_attempts' => 5,
            'error_message' => 'Payment failed.',
        ]);

        $response = $this->getJson('/api/webhooks');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta',
            ]);
    }

    public function test_webhook_events_can_be_filtered_by_status(): void
    {
        $this->authenticate();

        PaymentWebhookEvent::create([
            'event_id' => 'evt-query-003',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'txn-query-003',
            'payload' => [
                'event_id' => 'evt-query-003',
                'event_type' => 'payment.succeeded',
            ],
            'status' => 'PROCESSED',
            'attempts' => 1,
            'max_attempts' => 5,
            'processed_at' => now(),
        ]);

        PaymentWebhookEvent::create([
            'event_id' => 'evt-query-004',
            'event_type' => 'payment.failed',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'txn-query-004',
            'payload' => [
                'event_id' => 'evt-query-004',
                'event_type' => 'payment.failed',
            ],
            'status' => 'FAILED',
            'attempts' => 2,
            'max_attempts' => 5,
            'error_message' => 'Payment failed.',
        ]);

        $response = $this->getJson('/api/webhooks?status=FAILED');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.event_id', 'evt-query-004');
    }

    public function test_webhook_detail_can_be_retrieved(): void
    {
        $this->authenticate();

        $event = PaymentWebhookEvent::create([
            'event_id' => 'evt-query-detail-001',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'txn-query-detail-001',
            'payload' => [
                'event_id' => 'evt-query-detail-001',
                'event_type' => 'payment.succeeded',
            ],
            'status' => 'PROCESSED',
            'attempts' => 1,
            'max_attempts' => 5,
            'processed_at' => now(),
        ]);

        $response = $this->getJson(
            '/api/webhooks/' . $event->id
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.event_id', 'evt-query-detail-001');
    }

    public function test_unknown_webhook_returns_not_found(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/webhooks/999999');

        $response->assertNotFound();
    }

    public function test_webhook_events_support_pagination(): void
    {
        $this->authenticate();

        for ($i = 1; $i <= 15; $i++) {
            PaymentWebhookEvent::create([
                'event_id' => sprintf('evt-pagination-%03d', $i),
                'event_type' => 'payment.succeeded',
                'gateway' => 'mock',
                'gateway_transaction_id' => sprintf(
                    'txn-pagination-%03d',
                    $i
                ),
                'payload' => [
                    'event_id' => sprintf(
                        'evt-pagination-%03d',
                        $i
                    ),
                    'event_type' => 'payment.succeeded',
                ],
                'status' => 'PROCESSED',
                'attempts' => 1,
                'max_attempts' => 5,
                'processed_at' => now(),
            ]);
        }

        $response = $this->getJson(
            '/api/webhooks?per_page=10&page=1'
        );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ])
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 15);
    }

    public function test_webhook_events_require_authentication(): void
    {
        $response = $this->getJson('/api/webhooks');

        $response->assertUnauthorized();
    }

    public function test_webhook_detail_requires_authentication(): void
    {
        $event = PaymentWebhookEvent::create([
            'event_id' => 'evt-auth-detail-001',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'txn-auth-detail-001',
            'payload' => [
                'event_id' => 'evt-auth-detail-001',
                'event_type' => 'payment.succeeded',
            ],
            'status' => 'PROCESSED',
            'attempts' => 1,
            'max_attempts' => 5,
            'processed_at' => now(),
        ]);

        $response = $this->getJson(
            '/api/webhooks/' . $event->id
        );

        $response->assertUnauthorized();
    }
}