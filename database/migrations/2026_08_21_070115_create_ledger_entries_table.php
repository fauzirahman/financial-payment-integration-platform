<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('financial_transaction_id');
            $table->uuid('chart_of_account_id');

            $table->decimal('debit', 20, 2)->default(0);
            $table->decimal('credit', 20, 2)->default(0);

            $table->char('currency', 3);

            $table->text('description')->nullable();

            $table->timestamps();

            $table->foreign('financial_transaction_id')
                ->references('id')
                ->on('financial_transactions')
                ->cascadeOnDelete();

            $table->foreign('chart_of_account_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->restrictOnDelete();

            $table->index('financial_transaction_id');
            $table->index('chart_of_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};