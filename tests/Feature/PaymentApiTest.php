<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $assets = ChartOfAccount::create([
            'code' => '1000',
            'name' => 'Assets',
            'type' => 'ASSET',
            'normal_balance' => 'DEBIT',
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'code' => '1100',
            'name' => 'Cash / Bank',
            'type' => 'ASSET',
            'normal_balance' => 'DEBIT',
            'parent_id' => $assets->id,
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'code' => '4000',
            'name' => 'Payment Revenue',
            'type' => 'REVENUE',
            'normal_balance' => 'CREDIT',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'customer_number' => 'CUST-TEST-001',
            'name' => 'Test Customer',
            'email' => 'payment-test@example.com',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_payment_is_processed_and_posted_to_ledger(): void
    {
        $response = $this
            ->withHeader('Idempotency-Key', 'pay-test-001')
            ->postJson('/api/payments', [
                'payment_number' => 'PAY-TEST-001',
                'customer_id' => $this->customer->id,
                'amount' => '150000.00',
                'currency' => 'IDR',
                'method' => 'CARD',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('payments', [
            'payment_number' => 'PAY-TEST-001',
            'status' => 'SUCCESS',
        ]);

        $this->assertDatabaseCount('ledger_entries', 2);
        $this->assertDatabaseHas('idempotency_keys', [
            'key' => 'pay-test-001',
            'status' => 'COMPLETED',
        ]);
    }

    public function test_same_idempotency_key_replays_existing_payment(): void
    {
        $payload = [
            'payment_number' => 'PAY-TEST-002',
            'customer_id' => $this->customer->id,
            'amount' => '250000.00',
            'currency' => 'IDR',
            'method' => 'BANK_TRANSFER',
        ];

        $first = $this
            ->withHeader('Idempotency-Key', 'pay-test-002')
            ->postJson('/api/payments', $payload);
        $first->assertCreated();

        $second = $this
            ->withHeader('Idempotency-Key', 'pay-test-002')
            ->postJson('/api/payments', $payload);
        $second->assertOk()->assertJsonPath('success', true);

        $this->assertSame(
            $first->json('data.id'),
            $second->json('data.id')
        );
        $this->assertSame(1, Payment::count());
        $this->assertSame(2, \App\Models\LedgerEntry::count());
        $this->assertSame(1, IdempotencyKey::count());
    }

    public function test_payment_requires_idempotency_key(): void
    {
        $response = $this->postJson('/api/payments', [
            'payment_number' => 'PAY-TEST-003',
            'customer_id' => $this->customer->id,
            'amount' => '100000.00',
            'currency' => 'IDR',
            'method' => 'CARD',
        ]);

        $response
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Idempotency-Key header is required.',
            ]);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('idempotency_keys', 0);
    }

    public function test_same_idempotency_key_with_different_payload_is_rejected(): void
    {
        $firstPayload = [
            'payment_number' => 'PAY-TEST-004',
            'customer_id' => $this->customer->id,
            'amount' => '100000.00',
            'currency' => 'IDR',
            'method' => 'CARD',
        ];

        $secondPayload = [
            'payment_number' => 'PAY-TEST-005',
            'customer_id' => $this->customer->id,
            'amount' => '200000.00',
            'currency' => 'IDR',
            'method' => 'CARD',
        ];

        $first = $this
            ->withHeader('Idempotency-Key', 'pay-test-004')
            ->postJson('/api/payments', $firstPayload);

        $first->assertCreated();

        $second = $this
            ->withHeader('Idempotency-Key', 'pay-test-004')
            ->postJson('/api/payments', $secondPayload);

        $second
            ->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Idempotency key was already used with a different request.',
            ]);

        $this->assertSame(1, Payment::count());
        $this->assertSame(2, \App\Models\LedgerEntry::count());
        $this->assertSame(1, IdempotencyKey::count());
    }
}
