<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('event_id', 100)->unique();

            $table->string('event_type', 50);

            $table->string('gateway', 50);

            $table->string('gateway_transaction_id', 100);

            $table->json('payload');

            $table->string('status', 20)->default('RECEIVED');

            $table->timestamp('processed_at')->nullable();

            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index([
                'gateway',
                'gateway_transaction_id',
            ]);

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};