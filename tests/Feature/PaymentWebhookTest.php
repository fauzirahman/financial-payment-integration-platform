<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create([
            'customer_number' => 'CUST-WEBHOOK-001',
            'name' => 'Webhook Test Customer',
            'email' => 'webhook-test@example.com',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_successful_payment_webhook_updates_payment(): void
    {
        $payment = Payment::create([
            'payment_number' => 'PAY-WEBHOOK-001',
            'customer_id' => $this->customer->id,
            'amount' => '150000.00',
            'currency' => 'IDR',
            'method' => 'CARD',
            'status' => 'PENDING',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'mock-tx-webhook-001',
        ]);

        $response = $this->postJson('/api/webhooks/mock-payment', [
            'event_id' => 'evt-webhook-001',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'mock-tx-webhook-001',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Webhook processed successfully.',
            ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'SUCCESS',
        ]);

        $this->assertDatabaseHas('payment_webhook_events', [
            'event_id' => 'evt-webhook-001',
            'status' => 'PROCESSED',
        ]);
    }

    public function test_same_processed_webhook_event_is_idempotent(): void
    {
        $payment = Payment::create([
            'payment_number' => 'PAY-WEBHOOK-002',
            'customer_id' => $this->customer->id,
            'amount' => '250000.00',
            'currency' => 'IDR',
            'method' => 'BANK_TRANSFER',
            'status' => 'PENDING',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'mock-tx-webhook-002',
        ]);

        $payload = [
            'event_id' => 'evt-webhook-002',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'mock-tx-webhook-002',
        ];

        $first = $this->postJson(
            '/api/webhooks/mock-payment',
            $payload
        );

        $first->assertOk();

        $second = $this->postJson(
            '/api/webhooks/mock-payment',
            $payload
        );

        $second
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Webhook processed successfully.',
            ]);

        $this->assertSame(
            1,
            PaymentWebhookEvent::query()
                ->where('event_id', 'evt-webhook-002')
                ->count()
        );

        $this->assertSame(
            1,
            Payment::query()
                ->where('id', $payment->id)
                ->count()
        );

        $this->assertDatabaseHas('payment_webhook_events', [
            'event_id' => 'evt-webhook-002',
            'status' => 'PROCESSED',
        ]);
    }

    public function test_failed_webhook_event_is_persisted(): void
    {
        $response = $this->postJson('/api/webhooks/mock-payment', [
            'event_id' => 'evt-webhook-003',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'missing-payment-tx-003',
        ]);

        $response->assertStatus(500);

        $this->assertDatabaseHas('payment_webhook_events', [
            'event_id' => 'evt-webhook-003',
            'status' => 'FAILED',
            'error_message' => 'Payment not found for webhook event.',
        ]);
    }

    public function test_failed_webhook_can_be_retried_successfully(): void
    {
        $payload = [
            'event_id' => 'evt-webhook-004',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'retry-payment-tx-004',
        ];

        /*
         * First delivery fails because the payment does not exist yet.
         */
        $first = $this->postJson(
            '/api/webhooks/mock-payment',
            $payload
        );

        $first->assertStatus(500);

        $this->assertDatabaseHas('payment_webhook_events', [
            'event_id' => 'evt-webhook-004',
            'status' => 'FAILED',
        ]);

        /*
         * Payment becomes available before retry.
         */
        $payment = Payment::create([
            'payment_number' => 'PAY-WEBHOOK-004',
            'customer_id' => $this->customer->id,
            'amount' => '300000.00',
            'currency' => 'IDR',
            'method' => 'CARD',
            'status' => 'PENDING',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'retry-payment-tx-004',
        ]);

        /*
         * Retry the exact same webhook event.
         */
        $second = $this->postJson(
            '/api/webhooks/mock-payment',
            $payload
        );

        $second
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'SUCCESS',
        ]);

        $this->assertDatabaseHas('payment_webhook_events', [
            'event_id' => 'evt-webhook-004',
            'status' => 'PROCESSED',
            'error_message' => null,
        ]);

        $this->assertSame(
            1,
            PaymentWebhookEvent::query()
                ->where('event_id', 'evt-webhook-004')
                ->count()
        );
    }
}