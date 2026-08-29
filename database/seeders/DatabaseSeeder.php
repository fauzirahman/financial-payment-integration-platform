<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Finance Admin',
            'email' => 'admin@example.com',
        ]);

        $assets = ChartOfAccount::updateOrCreate(
            ['code' => '1000'],
            ['name' => 'Assets', 'type' => 'ASSET', 'normal_balance' => 'DEBIT', 'is_active' => true]
        );

        ChartOfAccount::updateOrCreate(
            ['code' => '1100'],
            ['name' => 'Cash / Bank', 'type' => 'ASSET', 'normal_balance' => 'DEBIT', 'parent_id' => $assets->id, 'is_active' => true]
        );

        ChartOfAccount::updateOrCreate(
            ['code' => '1200'],
            ['name' => 'Accounts Receivable', 'type' => 'ASSET', 'normal_balance' => 'DEBIT', 'parent_id' => $assets->id, 'is_active' => true]
        );

        ChartOfAccount::updateOrCreate(
            ['code' => '4000'],
            ['name' => 'Payment Revenue', 'type' => 'REVENUE', 'normal_balance' => 'CREDIT', 'is_active' => true]
        );

        Customer::updateOrCreate(
            ['customer_number' => 'CUST-0001'],
            [
                'name' => 'Demo Customer',
                'email' => 'customer@example.com',
                'phone' => '+628123456789',
                'status' => 'ACTIVE',
            ]
        );
    }
}
