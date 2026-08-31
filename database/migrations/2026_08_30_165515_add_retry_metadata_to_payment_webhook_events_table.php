<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_webhook_events', function (Blueprint $table) {
            $table->unsignedInteger('attempts')
                ->default(0)
                ->after('status');

            $table->unsignedInteger('max_attempts')
                ->default(5)
                ->after('attempts');

            $table->timestamp('next_retry_at')
                ->nullable()
                ->after('processed_at');

            $table->index([
                'status',
                'next_retry_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('payment_webhook_events', function (Blueprint $table) {
            $table->dropIndex([
                'payment_webhook_events_status_next_retry_at_index',
            ]);

            $table->dropColumn([
                'attempts',
                'max_attempts',
                'next_retry_at',
            ]);
        });
    }
};