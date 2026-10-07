<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerAccountApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_can_be_created_listed_and_retrieved(): void
    {
        $this->authenticate();

        $this->postJson('/api/customers', [
            'customer_number' => 'CUST-API-001',
            'name' => 'API Test Customer',
            'email' => 'customer-api@example.com',
            'phone' => '081234567890',
            'status' => 'SUSPENDED',
        ])
            ->assertCreated()
            ->assertJsonPath('data.customer_number', 'CUST-API-001')
            ->assertJsonPath('data.status', 'ACTIVE');

        $customer = Customer::where('customer_number', 'CUST-API-001')->firstOrFail();

        $this->getJson('/api/customers')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $customer->id);

        $this->getJson("/api/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.email', 'customer-api@example.com');
    }

    public function test_customer_endpoints_require_authentication(): void
    {
        $customer = $this->createCustomer();

        $this->getJson('/api/customers')->assertUnauthorized();
        $this->postJson('/api/customers', [])->assertUnauthorized();
        $this->getJson("/api/customers/{$customer->id}")->assertUnauthorized();
    }

    public function test_customer_creation_validates_unique_fields(): void
    {
        $this->authenticate();
        $this->createCustomer();

        $this->postJson('/api/customers', [
            'customer_number' => 'CUST-TEST-001',
            'name' => 'Duplicate Customer',
            'email' => 'customer-test@example.com',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_number', 'email']);
    }

    public function test_accounts_can_be_listed_and_created_for_a_customer(): void
    {
        $this->authenticate();
        $customer = $this->createCustomer();

        $this->postJson("/api/customers/{$customer->id}/accounts", [
            'account_number' => 'ACC-API-001',
            'currency' => 'IDR',
        ])
            ->assertCreated()
            ->assertJsonPath('data.customer_id', $customer->id)
            ->assertJsonPath('data.balance', '0.00')
            ->assertJsonPath('data.status', 'ACTIVE');

        $this->getJson("/api/customers/{$customer->id}/accounts")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.account_number', 'ACC-API-001');
    }

    public function test_account_creation_cannot_set_initial_balance_or_status(): void
    {
        $this->authenticate();
        $customer = $this->createCustomer();

        $this->postJson("/api/customers/{$customer->id}/accounts", [
            'account_number' => 'ACC-API-002',
            'currency' => 'IDR',
            'balance' => '999999.99',
            'status' => 'SUSPENDED',
        ])->assertCreated()
            ->assertJsonPath('data.balance', '0.00')
            ->assertJsonPath('data.status', 'ACTIVE');

        $this->assertSame('0.00', Account::where('account_number', 'ACC-API-002')->value('balance'));
    }

    public function test_account_endpoints_require_authentication(): void
    {
        $customer = $this->createCustomer();

        $this->getJson("/api/customers/{$customer->id}/accounts")->assertUnauthorized();
        $this->postJson("/api/customers/{$customer->id}/accounts", [])->assertUnauthorized();
    }

    public function test_accounts_cannot_be_created_for_an_unknown_customer(): void
    {
        $this->authenticate();

        $this->postJson('/api/customers/00000000-0000-0000-0000-000000000000/accounts', [
            'account_number' => 'ACC-API-003',
            'currency' => 'IDR',
        ])->assertNotFound();
    }

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    private function createCustomer(): Customer
    {
        return Customer::create([
            'customer_number' => 'CUST-TEST-001',
            'name' => 'Test Customer',
            'email' => 'customer-test@example.com',
            'status' => 'ACTIVE',
        ]);
    }
}