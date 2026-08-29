<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('payment_number', 40)->unique();

            $table->uuid('customer_id');

            $table->decimal('amount', 20, 2);

            $table->char('currency', 3);

            $table->string('method', 30);

            $table->string('status', 20)->default('PENDING');

            $table->string('gateway', 50)->nullable();

            $table->string('gateway_transaction_id', 100)->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->text('description')->nullable();

            $table->timestamps();

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->restrictOnDelete();

            $table->index(['customer_id', 'status']);
            $table->index('gateway_transaction_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};