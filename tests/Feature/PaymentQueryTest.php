<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentQueryTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create([
            'customer_number' => 'CUST-QUERY-001',
            'name' => 'Query Test Customer',
            'email' => 'query-test@example.com',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_payments_can_be_listed(): void
    {
        $this->authenticate();

        Payment::create([
            'payment_number' => 'PAY-QUERY-001',
            'customer_id' => $this->customer->id,
            'amount' => '100000.00',
            'currency' => 'IDR',
            'method' => 'CARD',
            'status' => 'SUCCESS',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'query-tx-001',
            'paid_at' => now(),
        ]);

        Payment::create([
            'payment_number' => 'PAY-QUERY-002',
            'customer_id' => $this->customer->id,
            'amount' => '200000.00',
            'currency' => 'IDR',
            'method' => 'BANK_TRANSFER',
            'status' => 'FAILED',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'query-tx-002',
        ]);

        $response = $this->getJson('/api/payments');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_payments_can_be_filtered_by_status(): void
    {
        $this->authenticate();

        Payment::create([
            'payment_number' => 'PAY-QUERY-003',
            'customer_id' => $this->customer->id,
            'amount' => '100000.00',
            'currency' => 'IDR',
            'method' => 'CARD',
            'status' => 'SUCCESS',
            'gateway' => 'mock',
        ]);

        Payment::create([
            'payment_number' => 'PAY-QUERY-004',
            'customer_id' => $this->customer->id,
            'amount' => '200000.00',
            'currency' => 'IDR',
            'method' => 'CARD',
            'status' => 'FAILED',
            'gateway' => 'mock',
        ]);

        $response = $this->getJson(
            '/api/payments?status=SUCCESS'
        );

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath(
                'data.0.payment_number',
                'PAY-QUERY-003'
            )
            ->assertJsonPath(
                'data.0.status',
                'SUCCESS'
            );
    }

    public function test_payment_detail_can_be_retrieved(): void
    {
        $this->authenticate();

        $payment = Payment::create([
            'payment_number' => 'PAY-QUERY-005',
            'customer_id' => $this->customer->id,
            'amount' => '350000.00',
            'currency' => 'IDR',
            'method' => 'CARD',
            'status' => 'SUCCESS',
            'gateway' => 'mock',
            'gateway_transaction_id' => 'query-tx-005',
            'paid_at' => now(),
        ]);

        $response = $this->getJson(
            "/api/payments/{$payment->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.id',
                $payment->id
            )
            ->assertJsonPath(
                'data.payment_number',
                'PAY-QUERY-005'
            )
            ->assertJsonPath(
                'data.status',
                'SUCCESS'
            );
    }

    public function test_unknown_payment_returns_not_found(): void
    {
        $this->authenticate();

        $response = $this->getJson(
            '/api/payments/00000000-0000-0000-0000-000000000000'
        );

        $response->assertNotFound();
    }

    public function test_payment_list_supports_pagination(): void
    {
        $this->authenticate();

        for ($i = 1; $i <= 3; $i++) {
            Payment::create([
                'payment_number' => sprintf(
                    'PAY-PAGE-%03d',
                    $i
                ),
                'customer_id' => $this->customer->id,
                'amount' => '100000.00',
                'currency' => 'IDR',
                'method' => 'CARD',
                'status' => 'SUCCESS',
                'gateway' => 'mock',
            ]);
        }

        $response = $this->getJson(
            '/api/payments?per_page=2'
        );

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(2, 'data');
    }

    private function authenticate(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);
    }

    public function test_payment_list_requires_authentication(): void
    {
        $response = $this->getJson('/api/payments');

        $response->assertUnauthorized();
    }

    public function test_payment_detail_requires_authentication(): void
    {
        $response = $this->getJson(
            '/api/payments/00000000-0000-0000-0000-000000000000'
        );

        $response->assertUnauthorized();
    }
}