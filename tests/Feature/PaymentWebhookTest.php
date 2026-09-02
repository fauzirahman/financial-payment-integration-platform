<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
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
            'status' => Payment::STATUS_PENDING,
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
            'status' => Payment::STATUS_SUCCESS,
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
            'status' => Payment::STATUS_PENDING,
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
            'status' => Payment::STATUS_PENDING,
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
            'status' => Payment::STATUS_SUCCESS,
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

    public function test_failed_webhook_schedules_retry_metadata(): void
    {
        $response = $this->postJson('/api/webhooks/mock-payment', [
            'event_id' => 'evt-webhook-retry-metadata',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'missing-payment-retry-metadata',
        ]);

        $response->assertStatus(500);

        $this->assertDatabaseHas('payment_webhook_events', [
            'event_id' => 'evt-webhook-retry-metadata',
            'status' => 'FAILED',
            'attempts' => 1,
            'max_attempts' => 5,
        ]);

        $event = PaymentWebhookEvent::query()
            ->where('event_id', 'evt-webhook-retry-metadata')
            ->firstOrFail();

        $this->assertNotNull($event->next_retry_at);
    }

    public function test_success_state_is_terminal(): void
    {
        $payment = Payment::create([
            'payment_number' => 'PAY-STATE-SUCCESS',
            'customer_id' => $this->customer->id,
            'amount' => '100000.00',
            'currency' => 'IDR',
            'method' => 'CARD',
            'status' => Payment::STATUS_SUCCESS,
            'gateway' => 'mock',
            'gateway_transaction_id' => 'mock-state-success',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Invalid payment state transition: SUCCESS -> FAILED.'
        );

        $payment->transitionTo(Payment::STATUS_FAILED);
    }

    public function test_failed_state_is_terminal(): void
    {
        $payment = Payment::create([
            'payment_number' => 'PAY-STATE-FAILED',
            'customer_id' => $this->customer->id,
            'amount' => '100000.00',
            'currency' => 'IDR',
            'method' => 'CARD',
            'status' => Payment::STATUS_FAILED,
            'gateway' => 'mock',
            'gateway_transaction_id' => 'mock-state-failed',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Invalid payment state transition: FAILED -> SUCCESS.'
        );

        $payment->transitionTo(Payment::STATUS_SUCCESS);
    }

    public function test_pending_can_transition_to_success(): void
    {
        $payment = Payment::create([
            'payment_number' => 'PAY-STATE-PENDING-SUCCESS',
            'customer_id' => $this->customer->id,
            'amount' => '100000.00',
            'currency' => 'IDR',
            'method' => 'CARD',
            'status' => Payment::STATUS_PENDING,
            'gateway' => 'mock',
            'gateway_transaction_id' => 'mock-state-pending-success',
        ]);

        $payment->transitionTo(Payment::STATUS_SUCCESS);
        $payment->save();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_SUCCESS,
        ]);
    }

    public function test_pending_can_transition_to_failed(): void
    {
        $payment = Payment::create([
            'payment_number' => 'PAY-STATE-PENDING-FAILED',
            'customer_id' => $this->customer->id,
            'amount' => '100000.00',
            'currency' => 'IDR',
            'method' => 'CARD',
            'status' => Payment::STATUS_PENDING,
            'gateway' => 'mock',
            'gateway_transaction_id' => 'mock-state-pending-failed',
        ]);

        $payment->transitionTo(Payment::STATUS_FAILED);
        $payment->save();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_FAILED,
        ]);
    }

    public function test_success_to_success_is_idempotent(): void
    {
        $payment = Payment::create([
            'payment_number' => 'PAY-STATE-SAME-SUCCESS',
            'customer_id' => $this->customer->id,
            'amount' => '100000.00',
            'currency' => 'IDR',
            'method' => 'CARD',
            'status' => Payment::STATUS_SUCCESS,
            'gateway' => 'mock',
            'gateway_transaction_id' => 'mock-state-same-success',
        ]);

        $payment->transitionTo(Payment::STATUS_SUCCESS);

        $this->assertSame(
            Payment::STATUS_SUCCESS,
            $payment->status
        );
    }

    public function test_failed_to_failed_is_idempotent(): void
    {
        $payment = Payment::create([
            'payment_number' => 'PAY-STATE-SAME-FAILED',
            'customer_id' => $this->customer->id,
            'amount' => '100000.00',
            'currency' => 'IDR',
            'method' => 'CARD',
            'status' => Payment::STATUS_FAILED,
            'gateway' => 'mock',
            'gateway_transaction_id' => 'mock-state-same-failed',
        ]);

        $payment->transitionTo(Payment::STATUS_FAILED);

        $this->assertSame(
            Payment::STATUS_FAILED,
            $payment->status
        );
    }

    public function test_webhook_cannot_move_failed_payment_to_success(): void
    {
        $payment = Payment::create([
            'payment_number' => 'PAY-WEBHOOK-STATE-FAILED',
            'customer_id' => $this->customer->id,
            'amount' => '100000.00',
            'currency' => 'IDR',
            'method' => 'CARD',
            'status' => Payment::STATUS_FAILED,
            'gateway' => 'mock',
            'gateway_transaction_id' => 'mock-tx-state-failed',
        ]);

        $response = $this->postJson('/api/webhooks/mock-payment', [
            'event_id' => 'evt-webhook-state-failed',
            'event_type' => 'payment.succeeded',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'mock-tx-state-failed',
        ]);

        $response->assertStatus(500);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_FAILED,
        ]);

        $this->assertDatabaseHas('payment_webhook_events', [
            'event_id' => 'evt-webhook-state-failed',
            'status' => 'FAILED',
        ]);
    }
}