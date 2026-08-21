<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('transaction_number', 40)->unique();

            $table->string('type', 30);

            $table->string('reference_type', 100)->nullable();

            $table->uuid('reference_id')->nullable();

            $table->text('description')->nullable();

            $table->timestamp('transaction_date');

            $table->string('status', 20)->default('PENDING');

            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('transaction_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};