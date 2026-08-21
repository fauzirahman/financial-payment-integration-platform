<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->string('account_number', 30)->unique();

            $table->char('currency', 3);

            $table->decimal('balance', 20, 2)->default(0);

            $table->string('status', 20)->default('ACTIVE');

            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index('currency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};