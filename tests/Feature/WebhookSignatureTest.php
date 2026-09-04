<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_signature_is_optional_when_no_secret_is_configured(): void
    {
        config(['services.mock_webhook.secret' => null]);

        $response = $this->postJson('/api/webhooks/mock-payment', [
            'event_id' => 'evt-signature-disabled',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'missing-signature-disabled',
        ]);

        $response->assertStatus(500);
    }

    public function test_webhook_rejects_invalid_signature_when_secret_is_configured(): void
    {
        config(['services.mock_webhook.secret' => 'portfolio-secret']);

        $response = $this
            ->withHeader('X-Webhook-Signature', 'invalid')
            ->postJson('/api/webhooks/mock-payment', [
                'event_id' => 'evt-signature-invalid',
                'event_type' => 'payment.succeeded',
                'gateway' => 'mock',
                'gateway_transaction_id' => 'missing-signature-invalid',
            ]);

        $response
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Invalid webhook signature.',
            ]);
    }

    public function test_webhook_accepts_valid_hmac_signature(): void
    {
        config(['services.mock_webhook.secret' => 'portfolio-secret']);

        $payload = [
            'event_id' => 'evt-signature-valid',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'missing-signature-valid',
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, 'portfolio-secret');

        $response = $this
            ->withHeader('X-Webhook-Signature', $signature)
            ->postJson('/api/webhooks/mock-payment', $payload);

        $response->assertStatus(500);
    }
}
